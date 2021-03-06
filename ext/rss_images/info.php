<?php declare(strict_types=1);

class RSSImagesInfo extends ExtensionInfo
{
    public const KEY = "rss_images";

    public $key = self::KEY;
    public $name = "RSS for Posts";
    public $url = self::SHIMMIE_URL;
    public $authors = self::SHISH_AUTHOR;
    public $license = self::LICENSE_GPLV2;
    public $description = "Self explanatory";
}
