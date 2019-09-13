# Hybris igdm

Hybris is an instagram direct messages client built on top of reactphp and instagram php private api.
Hybris includes all the benefit of a sleek cli ui and the reactivity of the instagram Realtime system.


# installation

Hybris depend on php and composer

```
git clone https://github.com/koso00/hybris.git
cd hybris
composer update
```

To start simply launch
```
php index.php
```

# Shorten media urls (and why)
You will not see the media you received in the chat, instead you will have a direct url to access the image/video/vocal on the cdn.
You can use a bitly token to shorten links of media in the chat, to not destroy them while breaking line.
```
echo "BITLY_TOKEN=YOUR_TOKEN_HERE" >> .env
```
# Usage

I't fairly simple, just login, then choose the chat you want and start chatting.
Press CTRL+E while in a chat to exit.
