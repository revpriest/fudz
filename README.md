# Fudz

We fuzz with feeds.


## Install 

Clone into your web-root, ideally in a directory called
"fudz" but where you put it is up to you.

Copy secrets_template.php to secrets.php and then edit
it so it knows where it lives and stuff.

Make sure cache directory exists and is writable by
the web-server (probably Apache? I'm using Apache)

Run composer to fetch the dependencies.

```
./composer.phar install
```

## Use

You added it in yourwebsite.com/fudz/ then say you
want an RSS feed of a twitter user, you just use

```
yourwebsite.com/fudz/mastou/boing.world/pre
```

to get an adapted RSS feed of my mastodon account.

Or if you wanna follow me on Twitter you can use

```
yourwebsite.com/fudz/twitteru/revpriest
```


Edit the secrets.php to make sure you add your
RSS reader's IP addresses and avoid running some
kinda open relay that could fill up your cache
if everyone was using it.


## Troubleshooting

Have you checked that apache allows the .htaccess allows all
the things it tries to do?

Have you checked the cache directory is writeable?

Have you fetched the dependencies with composer?

Can you tried running from to command line to check for
any errors?


