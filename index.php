<?php
namespace revpriest\fudz;
use Embed\Embed;
use Pharaonic\RSS\RSS;
use Pharaonic\RSS\RSSItem;
use TwitterNoAuth\Twitter;

include_once __DIR__ . "/vendor/autoload.php";

#$path = "/fudz/feed/boing.world/@pre.rss";
$path = "/fudz/twitteru/_ndrsn";

if(isset($_SERVER['REQUEST_URI'])){
  $path = $_SERVER['REQUEST_URI'];
}
$bits = explode("/",$path);
array_shift($bits);					//Start with a slash
$fudz = array_shift($bits);
$type = array_shift($bits);


header("Content-Type: text/xml;charset=UTF-8");
switch($type){
  case "feed":
		$host = array_shift($bits);
		$path = "/".implode("/",$bits);
    print processFeed("https://",$host,$path);
	  return;
  case "twitteru":
		$user = array_shift($bits);
    print processTwitterUser($user);
	  return;
  case "feedi":
    print processFeed("http://",$host,$path);
	  return;
   default:
}
print("Unknown command type");
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
function processFeed($method,$host,$path){
	$outFeed = new RSS();
	$outFeed->setTitle("Default RSS Title");
	$outFeed->setDescription("Default RSS Description");
	$outFeed->setLink("https://dalliance.net/fudz/feed/$host.$path");

  $pageContent = cache_fetch($method.$host.$path);
	try{
    $pageData = @new \SimpleXMLElement($pageContent);
	}catch(\Exception $e){
	}

	if($pageData!=null){
		//RSS
		if($pageData->getName()=="rss"){
			$outFeed->setTitle(trim($pageData->channel->title));
			$outFeed->setDescription(mb_convert_encoding(trim($pageData->channel->description), 'UTF-8', 'UTF-8'));
		  foreach($pageData->channel->item as $item){
				$outItem = new RSSItem();
				$outItem->setTitle(mb_convert_encoding(strim($item->title), 'UTF-8', 'UTF-8'));
				$outItem->setGuid("fudz-".trim($item->guid));
				$outItem->setLink(trim($item->link));
				$outItem->setPublished(trim($item->pubdate));
				$outItem->setDescription(mb_convert_encoding(trim($item->description), 'UTF-8', 'UTF-8'));
				$outFeed->setItem($outItem);
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
		if($content=""){return null;}
		return $content;
	}

	$some=false;
	$ptext=null;
	try{
	  $info = Embed::create($url);
		$ptext = "<div style=\"max-width: 20em; max-height: 5em;border:1px solid black;float:right;\">";
		if($info->image){
			$some=true;
			$ptext.="<img src=\"".$info->image."\" style=\"max-width:100%;max-height:100%\" />";
		}
		if($info->description){
			$some=true;
			$ptext.="<span style=\"\" >".$info->description."</span>";
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
			$ptext.=" <span style=\"\" >(".$info->author.")</span>";
		}
		$ptext.="</div>";
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
* Process a twitter suer
*/
function processTwitterUser($user){
  $twitterBase = "https://twitter.com";
	$outFeed = new RSS();
	$outFeed->setTitle("@$user Tweets");
	$outFeed->setDescription("@$user Tweets");
	$outFeed->setLink("https://dalliance.net/fudz/twitteru/$user");

	$url =  'https://api.twitter.com/1.1/statuses/user_timeline.json?screen_name='.$user.'&count=20&tweet_mode=extended';
	$pageData = cache_fetch_twitter($url);
	if($pageData!=null){
		$outFeed->setTitle("Tweets from $user");
		$outFeed->setDescription("Tweets from $user");
		$data = json_decode($pageData,true);
		$i=0;
		foreach($data as $d){
				$user = "<div style=\"width: 10em; display:block; float:left; border:2px solid black; background:white;\">";
				$user.= "<img style=\"margin-right: 0.3em\" src=\"".$d['user']['profile_image_url_https']."\" width=\"9em\" />";
				$user.= "<span><a href=\"$twitterBase/".$d['user']['screen_name']."\">".$d['user']['name']." (".$d['user']['screen_name'].")</a></span>";
				$user.="</div>";
				$outItem = new RSSItem();
				$outItem->setPublished(trim(date("D, d M Y H:i:s O", strtotime($d['created_at']))));
				$outItem->setTitle(htmlspecialchars(mb_convert_encoding(substr($d['full_text'],0,50),'UTF-8','UTF-8')));
				$text = $d['full_text'];
				$text = preg_replace("|\n|","<br/>\n",$text);

				$previewtext="";
				if(sizeof($d['entities']['urls'])>0){
					foreach($d['entities']['urls'] as $url){
						$text = preg_replace("|".$url['url']."|","<a href=\"".$url['expanded_url']."\">".$url['expanded_url']."</a>",$text);
						if($previewtext==""){
							$previewUrl = $url['expanded_url'];
							$ptext = getPreviewText($previewUrl);
							if($ptext!=null){
								$previewtext=$ptext;
							}
						}
					}
				}

				if($previewtext==""){
					if((isset($d['entities']['media'])&&(sizeof($d['entities']['media'])>0))){
						foreach($d['entities']['media'] as $media){
							$murl = "";
							if(isset($media['media_url_https'])){
								$murl = $media['media_url_https'];
							}else if(isset($media['media_url'])){
								$murl = $media['media_url'];
							}
							if($url!=""){
								$previewtext = "<img style=\"max-wdith:20em;max-height:20em;\" src=\"$murl\" />";
							}
						}
					}
				}

				if($previewtext==""){
					if($d['in_reply_to_status_id']!=null){
						$replytourl = "https://twitter.com/".$d['in_reply_to_screen_name']."/status/".$d['in_reply_to_status_id'];
						$some=false;
						$ptext = getPreviewText($replytourl);
						if($ptext!=null){
							$previewtext=$ptext;
						}
					}
				}

				if($previewtext==""){
					//Still nothing? What about a retweet? Was it a retweet? We could quote the thing he reweeted.
					if(isset($d['retweeted_status']) && ($d['retweeted_status']!=null)){
						$retweeturl = "https://twitter.com/".$d['retweeted_status']['user']['screen_name']."/status/".$d['retweeted_status']['id_str'];
					}
					$ptext = getPreviewText($retweeturl);
					if($ptext!=null){
						$previewtext=$ptext;
					}
				}

				$outItem->setDescription(mb_convert_encoding($user.$text.$previewtext, 'UTF-8', 'UTF-8'));

				$turl = $twitterBase."/".$d['user']['screen_name']."/status/".$d['id_str'];
				$outItem->setGuid($turl);
				$outItem->setLink($turl);
				$outFeed->setItem($outItem);
		}
		return $outFeed->render();
	}
  return "<h1>Dunno how to decode $host data at $path.</h1>\n<pre>".htmlspecialchars($pageContent)."</pre>";
}



