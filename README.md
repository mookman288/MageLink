# MageLink
## Make your own link shortener with your own domain

This is a code demonstration to guide new developers on a variety of ways that PHP can be used.

### Requirements

* Apache 2.4+ (Nginx see Notes)
* MySQL 5+
* PHP 7.4+

### Installation

Put the entire contents of the MageLink install in your domain's public directory and visit the URL to run the installer.

### Notes

Nginx compatibility can be achieved with this configuration file transcoded by Winginx:

```
autoindex off;

location ~ ^/(.*)\.sql(.*)$ {
  return 403;
}

location / {
  rewrite ^(.*)$ https://$http_host$request_uri redirect;
  if ($http_host ~* "^www\.(.*)$"){
    set $http_host_1 $1;
    rewrite ^(.*)$ https://$http_host_1/$1 redirect;
  }
  if (!-e $request_filename){
    rewrite ^(.+)$ /index.php?code=$1 break;
  }
}
```

## License

See LICENSE