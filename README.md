# Fudz

We fuzz with feeds.

Want to follow a Twitter user in your RSS feeds?

Want to mix people from Activity pub?

Want to fetch previews on that RSS feed which annoyingly
doesn't include previews when they link something?

That kind of thing is what this does.


## Install 

Clone into your web-root, ideally in a directory called
"fudz" but where you put it is up to you.

Copy secrets_template.php to secrets.php and then edit
it so it knows where it lives and stuff.

Make sure cache directory exists and is writeable by
the web-server (probably Apache? I'm using Apache,
you may need mad skillz to make it run elsewhere)

Run composer to fetch the dependencies.

```
./composer.phar install
```

## Use

If you added it in yourwebsite.com/fudz/ then, 
say you want an RSS feed of a mastodon user, 
you just use

```
yourwebsite.com/fudz/mastou/boing.world/pre
```

to get an adapted RSS feed of my mastodon account.

Or if you wanna follow me on Twitter you can use

```
yourwebsite.com/fudz/twitteru/revpriest
```

Or if you wanna follow my Rumble channel you can use

```
yourwebsite.com/fudz/rumbleu/starshipsd
```

There's also one for Rumble Channels but I don't
think I have one of those, you might have to be
important like Russell Brand.

---

If you edit those URLs to different mastodon
hosts or twitter usernames you can get feeds for
different people.

Edit the secrets.php to make sure you add your
RSS reader's IP addresses and avoid running some
kinda open relay that could fill up your cache
if everyone was using it.

Keep an eye on that cache size in general in fact.


## Troubleshooting

Have you checked that the .htaccess allows apache to read all
the things it tries to read?

Have you checked the cache directory is writeable?

Have you fetched the dependencies with composer?

Can you try running from the command line to check for
any errors printed to console?


