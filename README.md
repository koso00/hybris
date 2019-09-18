# Hybris igdm

Hybris is an instagram direct messages client built on top of reactphp and instagram php private api.
Hybris includes all the benefit of a sleek cli ui and the reactivity of the instagram Realtime system.

# Notes

Actually it's not behaving well in group chats.
It's simply untested.

# Installation

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


#State of the art

Actually it can run on Linux and MacOs (not tested but super sure!).
It can't run on Windows because of a php bug, see [here](https://bugs.php.net/bug.php?id=47918&thanks=6) the bug.

Things done :
    * Login
    * Chats view (complete)
    * Chat view (complete, almost)
    * Message sending

Actually there's something to do : 
    * Load more chat in chat selection screen (done)
    * Support media sending (soon)
    * Support message deletion (idk)
    * Support vocals message playback (idk)
    * Support vocal message sending (idk)
    * Support Windows (not feasible, neither if used in WLS dunno why)