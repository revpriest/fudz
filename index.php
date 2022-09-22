<?php
/**
* ToDo
*
* How can we publish this, and yet still use the
* open-cache under the url /fudz on my obvious
* website?
*
*/
namespace revpriest\fudz;
use Embed\Embed;
use Pharaonic\RSS\RSS;
use Pharaonic\RSS\RSSItem;
use TwitterNoAuth\Twitter;

include_once __DIR__ . "/vendor/autoload.php";


//You can override all these in a file "./secrets.php"
$secretsFile = "secrets.php";

//Default hostname is taken from $_SERVER but feel free to override it.
$hostname="localhost";
if(isset($_SERVER['HTTP_HOST'])){
  $hostname = $_SERVER['HTTP_HOST'];
}
#$hostname="localhost";

//The homepath is the directory in which fudz can be found.
$homepath="/fudz/";

//You may limit which IPs are allowed to connect
$allowedIps = [];

//You may allow some paths to be viewed even by other IPS.
$whitelistPaths = ["twitteru/revpriest","mastou/boing.world/pre","feed/https/boing.world/@pre.rss"];

//If you rewrite twitter URLS to nitter, share the load.
$nitterInstances = [
//  "nitter.net",			//Removed, always overloaded.
  "nitter.42l.fr",
  "nitter.pussthecat.org",
  "nitter.fdn.fr",
  "nitter.1d4.us",
  "nitter.kavin.rocks",
  "nitter.unixfox.eu"
];



//You can have a default path so you can run a CLI test
$path = $homepath."feed/https/boing.world/@pre.rss";
#$path = $homepath."twitteru/revpriest";
#$path = $homepath."mastou/boing.world/pre";
if(isset($_SERVER['REQUEST_URI'])){
  $path = $_SERVER['REQUEST_URI'];
}


$fudz = substr($path,0,strlen($homepath));
if($fudz === $homepath){
  $path = substr($path,strlen($homepath));
}else{
  $path = substr($path,1);	//Just a slash then? Weird.
}
$myHome = $hostname.$fudz;

//All that can be overwritten with a file called secrets.php:
if(file_exists(__DIR__ . "/".$secretsFile)){
  include_once __DIR__ . "/".$secretsFile;
}


//Have we limited by IP?
$allowed = true;
if(isset($_SERVER['REMOTE_ADDR'])){
	if(sizeof($allowedIps)>0){
		$allowed=false;	
		$ip = $_SERVER['REMOTE_ADDR'];
		foreach($allowedIps as $testIp){
			if($ip==$testIp){
				$allowed=true;
			}
		}
	}

	foreach($whitelistPaths as $wl){
	  if($path==$wl){
			$allowed=true;
		}
	}
}
if(!$allowed){
  print("I am not running on open relay here $ip!\n");
	print("Get your own Fudz at <a href=\"https://github.com/revpriest/fudz\">https://github.com/revpriest/fudz</a>");
//	file_put_contents("/home/pre/log/fudz.err","$ip denied $path\n",FILE_APPEND);
	exit;
}else{
//	file_put_contents("/home/pre/log/fudz.err","? $ip allowed $path\n",FILE_APPEND);
}


//Slashes divide our parameters then, what's the command type?
$bits = explode("/",$path);
while($bits[0]===""){array_shift($bits);}
$type = array_shift($bits);

header("Content-Type: text/xml;charset=UTF-8");
switch($type){
  case "feed":
		$http = array_shift($bits);
		$host = array_shift($bits);
		$path = "/".implode("/",$bits);
    print processFeed($http,$host,$path);
	  return;
  case "fetchpreview":
		$http = array_shift($bits);
		$host = array_shift($bits);
		$path = "/".implode("/",$bits);
    print processFeedWithPreview($http,$host,$path);
	  return;
  case "twitteru":
		$user = array_shift($bits);
    print processTwitterUser($user);
	  return;
  case "mastou":
  case "mastodonu":
		$host = array_shift($bits);
		$user = array_shift($bits);
    print processMastodonUser($host,$user);
	  return;
  case "feedi":
    print processFeed("http://",$host,$path);
	  return;
   default:
}
print("Unknown command type $type");
return;




/**
* Fetch a twtitter URL, with the bearer header and that,
* only if there's a version in the cache don't
* bother going to the web for it, use the cache
*/
function cache_fetch_twitter($url){
  $hash = md5($url);
	$dir = "cache/".substr($hash,0,2)."/".substr($hash,2,2);
  $fn = $dir."/".$hash.".cache";
	if((file_exists($fn)) && ((time()-filemtime($fn) < 10 * 60))){
	  $content=file_get_contents($fn);
	}else{
		$c = curl_init($url);
		$token = "AAAAAAAAAAAAAAAAAAAAAPYXBAAAAAAACLXUNDekMxqa8h%2F40K4moUkGsoc%3DTYfbDKbT3jJPCEVnMYqilB28NHfOPqkca3qaAxGfsyKCs0wRbw";
		curl_setopt($c, CURLOPT_HTTPHEADER, array(
		 'Content-Type: application/json',
		 'Authorization: Bearer ' . $token
		 ));
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		$content = curl_exec($c);
		curl_close($c);
    @mkdir($dir, 0777, true);
		file_put_contents($fn,$content);
	}
	return $content;
}



/**
* Fetch a url, only if there's a version in the cache don't
* bother going to the web for it, use the cache
*/
function cache_fetch($url){
  $hash = md5($url);
	$dir = "cache/".substr($hash,0,2)."/".substr($hash,2,2);
  $fn = $dir."/".$hash.".cache";
	if((file_exists($fn)) && ((time()-filemtime($fn) < 10 * 60))){
	  $content=file_get_contents($fn);
	}else{
		$content = file_get_contents($url);
    @mkdir($dir, 0777, true);
		file_put_contents($fn,$content);
	}
	return $content;
}


/**
* Process a feed
*/
function processFeed($http,$host,$path){
	global $myHome;
	$url = urldecode($http."://".$host.$path);

  $pageContent = cache_fetch($url);
	try{
    $pageData = @new \SimpleXMLElement($pageContent);
	}catch(\Exception $e){
	}

	$outFeed = new RSS();
	$outFeed->setTitle("Default RSS Title");
	$outFeed->setDescription("A copy of $url with few changes");
	$outFeed->setLink("https://$myHome"."feed/$http/$host$path");

	if($pageData!=null){
		//RSS
		if($pageData->getName()=="rss"){
			$outFeed->setTitle(trim($pageData->channel->title));
			$pageDesc = mb_convert_encoding(trim($pageData->channel->description), 'UTF-8', 'UTF-8');
			$outFeed->setDescription($pageDesc);
		  $numItems = sizeof($pageData->channel->item);
		  for($i=$numItems-1;$i>=0;$i--){
			  $item = $pageData->channel->item[$i];
				if($item){
					$ititle = mb_convert_encoding(trim($item->pubDate), 'UTF-8', 'UTF-8');
					$desc = mb_convert_encoding(trim($item->description), 'UTF-8', 'UTF-8');

					$outItem = new RSSItem();
					$outItem->setTitle($ititle);
					$outItem->setGuid(trim($item->guid));
					$outItem->setLink(trim($item->link));
					$outItem->setPublished(trim($item->pubdate));
					$outItem->setDescription($desc);
					$outFeed->setItem($outItem);
				}
			}
			return $outFeed->render();
		}
	}
  return "<h1>Dunno how to decode $host data at $path.</h1>\n<pre>".htmlspecialchars($pageContent)."</pre>";
}


/**
* Get preview text from a link. Mostly oauth and that.
* empty string for nothing to give.
*/
function getPreviewText($url){
	$hash = md5("Preview".$url);
	$dir = "cache/".substr($hash,0,2)."/".substr($hash,2,2);
  $fn = $dir."/".$hash.".cache";
	if((file_exists($fn)) && ((time()-filemtime($fn) < 24 * 60 * 60))){
	  $content=file_get_contents($fn);
		if($content==""){return null;}
		return $content;
	}

	$some=false;
	$ptext=null;
	try{
	  $info = Embed::create($url);
		$ptext = "";
		if($info->image){
			$some=true;
			$ptext.="<img src=\"".$info->image."\" />";
		}
		if($info->description){
			$some=true;
			$t = str_replace("<script","<scropt",$info->description);
			$t = str_replace("</script>","</scropt>",$t);
			$ptext.="<blockquote>".$t."</blockquote>";
		}
		if($info->code){
			$some=true;
			$code = $info->code;
			//Need to remove any <script> tags... Probably more than that really...
			$dom = new \DOMDocument();
			$dom->loadHTML($code,LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
			foreach (iterator_to_array($dom->getElementsByTagName("script")) as $item) {
					$item->parentNode->removeChild($item);
			}
			foreach (iterator_to_array($dom->getElementsByTagName("iframe")) as $item) {
					$item->parentNode->removeChild($item);
			}
			$code = $dom->saveHTML();
			$ptext.=$code;
		}
		if($info->author){
			$some=true;
			$ptext.=" (By ".$info->author.")";
		}
	}catch(\Exception $e){
		//Might be dead or blocked server.
		$some=false;
	}
	@mkdir($dir, 0777, true);
	if($some){
		@mkdir($dir, 0777, true);
		file_put_contents($fn,$ptext);
    return $ptext;
	}
	file_put_contents($fn,"");
	return null;
}



/**
* Process a Fedivers user though a mastodon API,
* well. It's RSS feed. Is that enough?
*/
function processMastodonUser($host,$user){
	global $myHome;
	$url = urldecode("https://$host/@$user.rss");
  $pageContent = cache_fetch($url);
	try{
    $pageData = @new \SimpleXMLElement($pageContent);
	}catch(\Exception $e){
	}

	$outFeed = new RSS();
	$outFeed->setTitle("Default Title $url");
	$outFeed->setDescription("A of the user at $url only with the page-previews fetched.");
	$outFeed->setLink("https://$myHome/mastou/$host/$user");

	if($pageData!=null){
		//RSS
		if($pageData->getName()=="rss"){
			$outFeed->setTitle(trim($pageData->channel->title));
			$pageDesc = mb_convert_encoding(trim($pageData->channel->description), 'UTF-8', 'UTF-8');
			$pageDesc.=" only with previews fetched for each page";
			$outFeed->setDescription($pageDesc);
	    $outFeed->setLink("https://$myHome/mastou/$host/$user");
		  $numItems = sizeof($pageData->channel->item);
		  for($i=$numItems-1;$i>=0;$i--){
			  $item = $pageData->channel->item[$i];
				if($item){
					$ititle = mb_convert_encoding(trim($item->pubDate), 'UTF-8', 'UTF-8');
					$desc = mb_convert_encoding(trim($item->description), 'UTF-8', 'UTF-8');

					$linkedPage = trim($item->link);
					$ptext = getPreviewText($linkedPage);
					$desc=$desc."<br/>--<br/>".$ptext;

					$outItem = new RSSItem();
					$outItem->setTitle($ititle);
					$outItem->setGuid(trim($item->guid));
					$outItem->setLink(trim($item->link));
					$outItem->setPublished(trim($item->pubdate));
					$outItem->setDescription($desc);
					$outFeed->setItem($outItem);
				}
			}
			return $outFeed->render();
		}
	  return $outFeed->render();
	}
  return "<h1>Dunno how to decode masto user $user data at $path.</h1>\n<pre>".htmlspecialchars($pageContent)."</pre>";
}



/**
* Process a New Grounds feed.
* They don't have images for some reason. Doh.
* So we'll have to fudz with them.
*/
function processFeedWithPreview($http,$host,$path){
	global $myHome;
	$url = urldecode($http."://".$host.$path);
  $pageContent = cache_fetch($url);
	try{
    $pageData = @new \SimpleXMLElement($pageContent);
	}catch(\Exception $e){
	}

	$outFeed = new RSS();
	$outFeed->setTitle("Default Title $url");
	$outFeed->setDescription("A feed like $url only with the page-previews fetched.");
	$outFeed->setLink("https://$myHome/fetchpreview/$http/$host$path");

	if($pageData!=null){
		//RSS
		if($pageData->getName()=="rss"){
			$outFeed->setTitle(trim($pageData->channel->title));
			$pageDesc = mb_convert_encoding(trim($pageData->channel->description), 'UTF-8', 'UTF-8');
			$pageDesc.=" only with previews fetched for each page";
			$outFeed->setDescription($pageDesc);
		  $numItems = sizeof($pageData->channel->item);
		  for($i=$numItems-1;$i>=0;$i--){
			  $item = $pageData->channel->item[$i];
				if($item){
					$ititle = mb_convert_encoding(trim($item->title), 'UTF-8', 'UTF-8');
					$desc = mb_convert_encoding(trim($item->description), 'UTF-8', 'UTF-8');

					$linkedPage = trim($item->link);
					$ptext = getPreviewText($linkedPage);
					$desc=$desc."<br/>--<br/>".$ptext;

					$outItem = new RSSItem();
					$outItem->setTitle($ititle);
					$outItem->setGuid(trim($item->guid));
					$outItem->setLink(trim($item->link));
					$outItem->setPublished(trim($item->pubdate));
					$outItem->setDescription($desc);
					$outFeed->setItem($outItem);
				}
			}
			return $outFeed->render();
		}
	  return $outFeed->render();
	}
  return "<h1>Dunno how to decode $host data at $path.</h1>\n<pre>".htmlspecialchars($pageContent)."</pre>";
}



/**
* Process a twitter suer
*/
function processTwitterUser($user){
	global $myHome;
	global $nitterInstances;
	$twitterBase = "https://twitter.com";
	$outFeed = new RSS();
	$outFeed->setTitle("@$user Tweets");
	$outFeed->setDescription("@$user Tweets");
	$outFeed->setLink("https://$myHome/twitteru/$user");

	$url =  'https://api.twitter.com/1.1/statuses/user_timeline.json?screen_name='.$user.'&count=20&tweet_mode=extended';
	$pageData = cache_fetch_twitter($url);
	if($pageData!=null){
		$outFeed->setTitle("Tweets from $user");
		$outFeed->setDescription("Tweets from $user");
		$data = json_decode($pageData,true);
		$i=0;
		foreach($data as $d){
				$textt = mb_convert_encoding($d['full_text'],'UTF-8','UTF-8');
				$text = preg_replace("|\n|","<br/>\n",$textt);
				$turl = $twitterBase."/".$d['user']['screen_name']."/status/".$d['id_str'];
				$title = htmlspecialchars(substr($textt,0,50));
				if(($title=="")||($title==null)){
					$title="Tweet";
				}

				
				$user = "<b>User:</b><br/>\n";
				$user.= "<img style=\"margin-right: 0.3em\" src=\"".$d['user']['profile_image_url_https']."\" width=\"9em\" />";
				$user.= "<span><a href=\"$turl\">".$d['user']['name']." (".$d['user']['screen_name'].")</a></span>";
				$user.= "<br/><br/>";

				$forewardtext = "";
				$previewtext="<br/><br/>---<br/><br/>";

				//Reply to something? - That goes in above the main tweet
				if($d['in_reply_to_status_id']!=null){
					$replytourl = "https://twitter.com/".$d['in_reply_to_screen_name']."/status/".$d['in_reply_to_status_id'];
					$some=false;
					$ptext = getPreviewText($replytourl);
					if($ptext!=null){
						$forewardtext.="<br/><b>Reply To:</b><br/>\n";
						$forewardtext.=$ptext;
						$forewardtext.="<br/>\n";
					}
					//If it's a self-reply, that's a thread. Change the title
					//so it won't be marked as a reply.
					if($d['in_reply_to_screen_name'] == $d['user']['screen_name']){	
						$title="Thread ".$title;
					}
				}

				//What about a retweet? Was it a retweet? We could quote the thing he reweeted.
				if(isset($d['retweeted_status']) && ($d['retweeted_status']!=null)){
					$retweeturl = "https://twitter.com/".$d['retweeted_status']['user']['screen_name']."/status/".$d['retweeted_status']['id_str'];
					$ptext = getPreviewText($retweeturl);
					if($ptext!=null){
						$forewardtext.="<br/><b>Retweet Of:</b><br/>\n";
						$forewardtext.=$ptext;
						$forewardtext.="<br/>\n";
					}
				}


				//Attached Media?
				if((isset($d['entities']['media'])&&(sizeof($d['entities']['media'])>0))){
					foreach($d['entities']['media'] as $media){
						$murl = "";
						if(isset($media['media_url_https'])){
							$murl = $media['media_url_https'];
						}else if(isset($media['media_url'])){
							$murl = $media['media_url'];
						}
						if($murl!=""){
							$previewtext.="<br/><b>Media:</b><br/>\n";
							$previewtext = "<img src=\"$murl\" />";
							$previewtext.="<br/><br/>\n";
						}
					}
				}


				//Links?
				if(sizeof($d['entities']['urls'])>0){
					$donelink=false;
					foreach($d['entities']['urls'] as $url){
						$text = preg_replace("|".$url['url']."|","<a href=\"".$url['expanded_url']."\">".$url['expanded_url']."</a>",$text);
						if($donelink==false){
							$previewUrl = $url['expanded_url'];
							$ptext = getPreviewText($previewUrl);
							if($ptext!=null){
								$previewtext.="<br/><b>FirstLink:</b><br/>".$ptext."<br/><br/><\n";
							  $donelink=true;
							}
						}
					}
				}

				$outItem = new RSSItem();
				$outItem->setPublished(trim(date("D, d M Y H:i:s O", strtotime($d['created_at']))));
				if(($text==null)||($text=="")){
					print("Blank");exit;
				}
				$finalText = $user.$forewardtext.$text.$previewtext;

				//Let's use nitter.net if we can
				$nitterInstance = $nitterInstances[intval(rand(0,count($nitterInstances)-1))];
				$finalText = preg_replace("|twitter.com/|","$nitterInstance/",$finalText);
				$trurl = preg_replace("|twitter.com/|","$nitterInstance/",$turl);


				$outItem->setTitle($title);
				$outItem->setGuid($turl);
				$outItem->setLink($trurl);
				$outItem->setDescription($finalText);
				$outFeed->setItem($outItem);
				$i++;
		}
		return $outFeed->render();
	}
  return "<h1>Dunno how to decode $host data at $path.</h1>\n<pre>".htmlspecialchars($pageContent)."</pre>";
}



