# Create your own cache server

Base : [https://blog.lael.be/post/7605](https://blog.lael.be/post/7605)\
Blog post : [https://marshall-ku.com/web/tips/캐시-서버-구축하기](https://marshall-ku.com/web/tips/캐시-서버-구축하기)

## Features

-   Cache Images, Videos (png, jpg, jpeg, gif, webp, mp4, webm, svg)
-   Create WebP Images, and cache them

Just place `.webp` after the image's url to convert them in webp files.\
`eg. https://example.com/src/images/tmp.png.webp`

## How to Install

    You might use PHP lower than 8.0

Replace `example.com` to your domain.\
You should update both nginx.conf and index.php.

### Create Nginx configuration file

Create your own nginx configuration file with [nginx.conf](https://github.com/marshall-ku/Simple-CDN/blob/master/nginx/nginx.conf) file in `/etc/nginx/sites-available`

    sudo ln -s /etc/nginx/sites-available/example.com /etc/nginx/sites-enabled/

and link that file in `/etc/nginx/sites-available`

### Add files in web root folder

Place every files in [www](https://github.com/marshall-ku/Simple-CDN/tree/master/www) folder in `/home/cdn/www`

### Restart Nginx, PHP-fpm

    sudo systemctl nginx restart
    sudo systemctl php8.0-fpm restart

### Replace every images' link

```PHP
function imgToPicture($content)
{
    preg_match_all('/<img(.+?)(src=\"(.+?)\")(.+?)(srcset="(.+?)")? ?\/?>/', $content, $matches);
    $i = 0;
    foreach ($matches[0] as &$image) {
        // Replace domain to cache server domain
        // Replaces https://example.com/img.png to https://img.example.com/img.png
        $src = str_replace('//example', '//img.example', $matches[3][$i]);
        $srcset = $matches[6][$i] ?? '';
        $srcset = $srcset != '' ? preg_replace('/\/\/example/', '//img.example', $srcset) : '';

        $webpSrcset = $srcset != '' ? preg_replace('/\.(png|jpe?g)/', '.$1.webp', $srcset) : '';

        $tmp = "<picture><source type=\"image/webp\" srcset=\"{$src}.webp 1x,{$webpSrcset}\"></source><source srcset=\"{$src} 1x,{$srcset}\"></source><img{$matches[1][$i]}src=\"{$src}\"{$matches[4][$i]}srcset=\"{$srcset}\"></picture>";

        $content = str_replace($image, $tmp, $content);
        $i++;
    }
    return $content;
}

add_filter('the_content', 'imgToPicture');
```

Simple example with the code that I used for my wordpress theme.
