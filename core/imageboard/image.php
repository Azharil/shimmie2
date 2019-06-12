<?php
/**
 * Class Image
 *
 * An object representing an entry in the images table.
 *
 * As of 2.2, this no longer necessarily represents an
 * image per se, but could be a video, sound file, or any
 * other supported upload type.
 */
class Image
{
    private static $tag_n = 0; // temp hack
    public static $order_sql = null; // this feels ugly

    /** @var null|int */
    public $id = null;

    /** @var int */
    public $height;

    /** @var int */
    public $width;

    /** @var string */
    public $hash;

    public $filesize;

    /** @var string */
    public $filename;

    /** @var string */
    public $ext;

    /** @var string[]|null */
    public $tag_array;

    /** @var int */
    public $owner_id;
    
    /** @var string */
    public $owner_ip;
    
    /** @var string */
    public $posted;
    
    /** @var string */
    public $source;
    
    /** @var boolean */
    public $locked = false;

    /**
     * One will very rarely construct an image directly, more common
     * would be to use Image::by_id, Image::by_hash, etc.
     */
    public function __construct(?array $row=null)
    {
        if (!is_null($row)) {
            foreach ($row as $name => $value) {
                // some databases use table.name rather than name
                $name = str_replace("images.", "", $name);
                $this->$name = $value; // hax, this is likely the cause of much scrutinizer-ci complaints.
            }
            $this->locked = bool_escape($this->locked);

            assert(is_numeric($this->id));
            assert(is_numeric($this->height));
            assert(is_numeric($this->width));
        }
    }

    public static function by_id(int $id): ?Image
    {
        global $database;
        $row = $database->get_row("SELECT * FROM images WHERE images.id=:id", ["id"=>$id]);
        return ($row ? new Image($row) : null);
    }

    public static function by_hash(string $hash): ?Image
    {
        global $database;
        $row = $database->get_row("SELECT images.* FROM images WHERE hash=:hash", ["hash"=>$hash]);
        return ($row ? new Image($row) : null);
    }

    public static function by_random(array $tags=[]): ?Image
    {
        $max = Image::count_images($tags);
        if ($max < 1) {
            return null;
        }		// From Issue #22 - opened by HungryFeline on May 30, 2011.
        $rand = mt_rand(0, $max-1);
        $set = Image::find_images($rand, 1, $tags);
        if (count($set) > 0) {
            return $set[0];
        } else {
            return null;
        }
    }

    /**
     * Search for an array of images
     *
     * #param string[] $tags
     * #return Image[]
     */
    public static function find_images(int $start, int $limit, array $tags=[]): array
    {
        global $database, $user, $config;

        $images = [];

        if ($start < 0) {
            $start = 0;
        }
        if ($limit < 1) {
            $limit = 1;
        }

        if (SPEED_HAX) {
            if (!$user->can("big_search") and count($tags) > 3) {
                throw new SCoreException("Anonymous users may only search for up to 3 tags at a time");
            }
        }

        $result = null;
        if (SEARCH_ACCEL) {
            $result = Image::get_accelerated_result($tags, $start, $limit);
        }

        if (!$result) {
            $querylet = Image::build_search_querylet($tags);
            $querylet->append(new Querylet(" ORDER BY ".(Image::$order_sql ?: "images.".$config->get_string("index_order"))));
            $querylet->append(new Querylet(" LIMIT :limit OFFSET :offset", ["limit"=>$limit, "offset"=>$start]));
            #var_dump($querylet->sql); var_dump($querylet->variables);
            $result = $database->execute($querylet->sql, $querylet->variables);
        }

        while ($row = $result->fetch()) {
            $images[] = new Image($row);
        }
        Image::$order_sql = null;
        return $images;
    }

    /**
     * Search for an array of image IDs
     *
     * #param string[] $tags
     * #return int[]
     */
    public static function find_image_ids(int $start, int $limit, array $tags=[]): array
    {
        global $database, $user, $config;

        $images = [];

        if ($start < 0) {
            $start = 0;
        }
        if ($limit < 1) {
            $limit = 1;
        }

        if (SPEED_HAX) {
            if (!$user->can("big_search") and count($tags) > 3) {
                throw new SCoreException("Anonymous users may only search for up to 3 tags at a time");
            }
        }

        $result = null;
        if (SEARCH_ACCEL) {
            $result = Image::get_accelerated_result($tags, $start, $limit);
        }

        if (!$result) {
            $querylet = Image::build_search_querylet($tags);
            $querylet->append(new Querylet(" ORDER BY ".(Image::$order_sql ?: "images.".$config->get_string("index_order"))));
            $querylet->append(new Querylet(" LIMIT :limit OFFSET :offset", ["limit"=>$limit, "offset"=>$start]));
            #var_dump($querylet->sql); var_dump($querylet->variables);
            $result = $database->execute($querylet->sql, $querylet->variables);
        }

        while ($row = $result->fetch()) {
            $images[] = $row["id"];
        }
        Image::$order_sql = null;
        return $images;
    }

    /*
     * Accelerator stuff
     */
    public static function get_acceleratable(array $tags): ?array
    {
        $ret = [
            "yays" => [],
            "nays" => [],
        ];
        $yays = 0;
        $nays = 0;
        foreach ($tags as $tag) {
            if (!preg_match("/^-?[a-zA-Z0-9_-]+$/", $tag)) {
                return null;
            }
            if ($tag[0] == "-") {
                $nays++;
                $ret["nays"][] = substr($tag, 1);
            } else {
                $yays++;
                $ret["yays"][] = $tag;
            }
        }
        if ($yays > 1 || $nays > 0) {
            return $ret;
        }
        return null;
    }

    public static function get_accelerated_result(array $tags, int $offset, int $limit): ?PDOStatement
    {
        global $database;

        $req = Image::get_acceleratable($tags);
        if (!$req) {
            return null;
        }
        $req["offset"] = $offset;
        $req["limit"] = $limit;

        $response = Image::query_accelerator($req);
        $list = implode(",", $response);
        if ($list) {
            $result = $database->execute("SELECT * FROM images WHERE id IN ($list) ORDER BY images.id DESC");
        } else {
            $result = $database->execute("SELECT * FROM images WHERE 1=0 ORDER BY images.id DESC");
        }
        return $result;
    }

    public static function get_accelerated_count(array $tags): ?int
    {
        $req = Image::get_acceleratable($tags);
        if (!$req) {
            return null;
        }
        $req["count"] = true;

        return Image::query_accelerator($req);
    }

    public static function query_accelerator($req)
    {
        $fp = @fsockopen("127.0.0.1", 21212);
        if (!$fp) {
            return null;
        }
        fwrite($fp, json_encode($req));
        $data = "";
        while (($buffer = fgets($fp, 4096)) !== false) {
            $data .= $buffer;
        }
        if (!feof($fp)) {
            die("Error: unexpected fgets() fail in query_accelerator($req)\n");
        }
        fclose($fp);
        return json_decode($data);
    }

    /*
     * Image-related utility functions
     */

    /**
     * Count the number of image results for a given search
     *
     * #param string[] $tags
     */
    public static function count_images(array $tags=[]): int
    {
        global $database;
        $tag_count = count($tags);

        if ($tag_count === 0) {
            $total = $database->cache->get("image-count");
            if (!$total) {
                $total = $database->get_one("SELECT COUNT(*) FROM images");
                $database->cache->set("image-count", $total, 600);
            }
        } elseif ($tag_count === 1 && !preg_match("/[:=><\*\?]/", $tags[0])) {
            $total = $database->get_one(
                $database->scoreql_to_sql("SELECT count FROM tags WHERE SCORE_STRNORM(tag) = SCORE_STRNORM(:tag)"),
                ["tag"=>$tags[0]]
            );
        } else {
            $total = Image::get_accelerated_count($tags);
            if (is_null($total)) {
                $querylet = Image::build_search_querylet($tags);
                $total = $database->get_one("SELECT COUNT(*) AS cnt FROM ($querylet->sql) AS tbl", $querylet->variables);
            }
        }
        if (is_null($total)) {
            return 0;
        }
        return $total;
    }

    /**
     * Count the number of pages for a given search
     *
     * #param string[] $tags
     */
    public static function count_pages(array $tags=[]): float
    {
        global $config;
        return ceil(Image::count_images($tags) / $config->get_int('index_images'));
    }

    /*
     * Accessors & mutators
     */

    /**
     * Find the next image in the sequence.
     *
     * Rather than simply $this_id + 1, one must take into account
     * deleted images and search queries
     *
     * #param string[] $tags
     */
    public function get_next(array $tags=[], bool $next=true): ?Image
    {
        global $database;

        if ($next) {
            $gtlt = "<";
            $dir = "DESC";
        } else {
            $gtlt = ">";
            $dir = "ASC";
        }

        if (count($tags) === 0) {
            $row = $database->get_row('
				SELECT images.*
				FROM images
				WHERE images.id '.$gtlt.' '.$this->id.'
				ORDER BY images.id '.$dir.'
				LIMIT 1
			');
        } else {
            $tags[] = 'id'. $gtlt . $this->id;
            $querylet = Image::build_search_querylet($tags);
            $querylet->append_sql(' ORDER BY images.id '.$dir.' LIMIT 1');
            $row = $database->get_row($querylet->sql, $querylet->variables);
        }

        return ($row ? new Image($row) : null);
    }

    /**
     * The reverse of get_next
     *
     * #param string[] $tags
     */
    public function get_prev(array $tags=[]): ?Image
    {
        return $this->get_next($tags, false);
    }

    /**
     * Find the User who owns this Image
     */
    public function get_owner(): User
    {
        return User::by_id($this->owner_id);
    }

    /**
     * Set the image's owner.
     */
    public function set_owner(User $owner): void
    {
        global $database;
        if ($owner->id != $this->owner_id) {
            $database->execute("
				UPDATE images
				SET owner_id=:owner_id
				WHERE id=:id
			", ["owner_id"=>$owner->id, "id"=>$this->id]);
            log_info("core_image", "Owner for Image #{$this->id} set to {$owner->name}", null, ["image_id" => $this->id]);
        }
    }

    /**
     * Get this image's tags as an array.
     *
     * #return string[]
     */
    public function get_tag_array(): array
    {
        global $database;
        if (!isset($this->tag_array)) {
            $this->tag_array = $database->get_col("
				SELECT tag
				FROM image_tags
				JOIN tags ON image_tags.tag_id = tags.id
				WHERE image_id=:id
				ORDER BY tag
			", ["id"=>$this->id]);
        }
        return $this->tag_array;
    }

    /**
     * Get this image's tags as a string.
     */
    public function get_tag_list(): string
    {
        return Tag::implode($this->get_tag_array());
    }

    /**
     * Get the URL for the full size image
     */
    public function get_image_link(): string
    {
        return $this->get_link('image_ilink', '_images/$hash/$id%20-%20$tags.$ext', 'image/$id.$ext');
    }

    /**
     * Get the nicely formatted version of the file name
     */
    public function get_nice_image_name(): string
    {
        return $this->parse_link_template('$id - $tags.$ext');
    }

    /**
     * Get the URL for the thumbnail
     */
    public function get_thumb_link(): string
    {
        global $config;
        $ext = $config->get_string("thumb_type");
        return $this->get_link('image_tlink', '_thumbs/$hash/thumb.'.$ext, 'thumb/$id.'.$ext);
    }

    /**
     * Check configured template for a link, then try nice URL, then plain URL
     */
    private function get_link(string $template, string $nice, string $plain): string
    {
        global $config;

        $image_link = $config->get_string($template);

        if (!empty($image_link)) {
            if (!(strpos($image_link, "://") > 0) && !startsWith($image_link, "/")) {
                $image_link = make_link($image_link);
            }
            return $this->parse_link_template($image_link);
        } elseif ($config->get_bool('nice_urls', false)) {
            return $this->parse_link_template(make_link($nice));
        } else {
            return $this->parse_link_template(make_link($plain));
        }
    }

    /**
     * Get the tooltip for this image, formatted according to the
     * configured template.
     */
    public function get_tooltip(): string
    {
        global $config;
        $tt = $this->parse_link_template($config->get_string('image_tip'), "no_escape");

        // Removes the size tag if the file is an mp3
        if ($this->ext === 'mp3') {
            $iitip = $tt;
            $mp3tip = ["0x0"];
            $h_tip = str_replace($mp3tip, " ", $iitip);

            // Makes it work with a variation of the default tooltips (I.E $tags // $filesize // $size)
            $justincase = ["   //", "//   ", "  //", "//  ", "  "];
            if (strstr($h_tip, "  ")) {
                $h_tip = html_escape(str_replace($justincase, "", $h_tip));
            } else {
                $h_tip = html_escape($h_tip);
            }
            return $h_tip;
        } else {
            return $tt;
        }
    }

    /**
     * Figure out where the full size image is on disk.
     */
    public function get_image_filename(): string
    {
        return warehouse_path("images", $this->hash);
    }

    /**
     * Figure out where the thumbnail is on disk.
     */
    public function get_thumb_filename(): string
    {
        return warehouse_path("thumbs", $this->hash);
    }

    /**
     * Get the original filename.
     */
    public function get_filename(): string
    {
        return $this->filename;
    }

    /**
     * Get the image's mime type.
     */
    public function get_mime_type(): string
    {
        return getMimeType($this->get_image_filename(), $this->get_ext());
    }

    /**
     * Get the image's filename extension
     */
    public function get_ext(): string
    {
        return $this->ext;
    }

    /**
     * Get the image's source URL
     */
    public function get_source(): ?string
    {
        return $this->source;
    }

    /**
     * Set the image's source URL
     */
    public function set_source(string $new_source): void
    {
        global $database;
        $old_source = $this->source;
        if (empty($new_source)) {
            $new_source = null;
        }
        if ($new_source != $old_source) {
            $database->execute("UPDATE images SET source=:source WHERE id=:id", ["source"=>$new_source, "id"=>$this->id]);
            log_info("core_image", "Source for Image #{$this->id} set to: $new_source (was $old_source)", null, ["image_id" => $this->id]);
        }
    }

    /**
     * Check if the image is locked.
     */
    public function is_locked(): bool
    {
        return $this->locked;
    }

    public function set_locked(bool $tf): void
    {
        global $database;
        $ln = $tf ? "Y" : "N";
        $sln = $database->scoreql_to_sql('SCORE_BOOL_'.$ln);
        $sln = str_replace("'", "", $sln);
        $sln = str_replace('"', "", $sln);
        if (bool_escape($sln) !== $this->locked) {
            $database->execute("UPDATE images SET locked=:yn WHERE id=:id", ["yn"=>$sln, "id"=>$this->id]);
            log_info("core_image", "Setting Image #{$this->id} lock to: $ln", null, ["image_id" => $this->id]);
        }
    }

    /**
     * Delete all tags from this image.
     *
     * Normally in preparation to set them to a new set.
     */
    public function delete_tags_from_image(): void
    {
        global $database;
        if ($database->get_driver_name() == "mysql") {
            //mysql < 5.6 has terrible subquery optimization, using EXISTS / JOIN fixes this
            $database->execute(
                "
				UPDATE tags t
				INNER JOIN image_tags it ON t.id = it.tag_id
				SET count = count - 1
				WHERE it.image_id = :id",
                ["id"=>$this->id]
            );
        } else {
            $database->execute("
				UPDATE tags
				SET count = count - 1
				WHERE id IN (
					SELECT tag_id
					FROM image_tags
					WHERE image_id = :id
				)
			", ["id"=>$this->id]);
        }
        $database->execute("
			DELETE
			FROM image_tags
			WHERE image_id=:id
		", ["id"=>$this->id]);
    }

    /**
     * Set the tags for this image.
     */
    public function set_tags(array $unfiltered_tags): void
    {
        global $database;

        $unfiltered_tags = array_unique($unfiltered_tags);

        $tags = [];
        foreach ($unfiltered_tags as $tag) {
            if (mb_strlen($tag, 'UTF-8') > 255) {
                flash_message("Can't set a tag longer than 255 characters");
                continue;
            }
            if (startsWith($tag, "-")) {
                flash_message("Can't set a tag which starts with a minus");
                continue;
            }

            $tags[] = $tag;
        }

        if (count($tags) <= 0) {
            throw new SCoreException('Tried to set zero tags');
        }

        if (Tag::implode($tags) != $this->get_tag_list()) {
            // delete old
            $this->delete_tags_from_image();

            $written_tags = [];

            // insert each new tags
            foreach ($tags as $tag) {
                $id = $database->get_one(
                    $database->scoreql_to_sql("
						SELECT id
						FROM tags
						WHERE SCORE_STRNORM(tag) = SCORE_STRNORM(:tag)
					"),
                    ["tag"=>$tag]
                );
                if (empty($id)) {
                    // a new tag
                    $database->execute(
                        "INSERT INTO tags(tag) VALUES (:tag)",
                        ["tag"=>$tag]
                    );
                    $database->execute(
                        "INSERT INTO image_tags(image_id, tag_id)
							VALUES(:id, (SELECT id FROM tags WHERE tag = :tag))",
                        ["id"=>$this->id, "tag"=>$tag]
                    );
                } else {
                    // check if tag has already been written
                    if(in_array($id, $written_tags)) {
                        continue;
                    }

                    $database->execute("
                        INSERT INTO image_tags(image_id, tag_id)
                        VALUES(:iid, :tid)
                    ", ["iid"=>$this->id, "tid"=>$id]);

                    array_push($written_tags, $id);
                }
                $database->execute(
                    $database->scoreql_to_sql("
						UPDATE tags
						SET count = count + 1
						WHERE SCORE_STRNORM(tag) = SCORE_STRNORM(:tag)
					"),
                    ["tag"=>$tag]
                );
            }

            log_info("core_image", "Tags for Image #{$this->id} set to: ".Tag::implode($tags), null, ["image_id" => $this->id]);
            $database->cache->delete("image-{$this->id}-tags");
        }
    }

    /**
     * Send list of metatags to be parsed.
     *
     * #param string[] $metatags
     */
    public function parse_metatags(array $metatags, int $image_id): void
    {
        foreach ($metatags as $tag) {
            $ttpe = new TagTermParseEvent($tag, $image_id, true);
            send_event($ttpe);
        }
    }

    /**
     * Delete this image from the database and disk
     */
    public function delete(): void
    {
        global $database;
        $this->delete_tags_from_image();
        $database->execute("DELETE FROM images WHERE id=:id", ["id"=>$this->id]);
        log_info("core_image", 'Deleted Image #'.$this->id.' ('.$this->hash.')', null, ["image_id" => $this->id]);

        unlink($this->get_image_filename());
        unlink($this->get_thumb_filename());
    }

    /**
     * This function removes an image (and thumbnail) from the DISK ONLY.
     * It DOES NOT remove anything from the database.
     */
    public function remove_image_only(): void
    {
        log_info("core_image", 'Removed Image File ('.$this->hash.')', null, ["image_id" => $this->id]);
        @unlink($this->get_image_filename());
        @unlink($this->get_thumb_filename());
    }

    public function parse_link_template(string $tmpl, string $_escape="url_escape", int $n=0): string
    {
        global $config;

        // don't bother hitting the database if it won't be used...
        $tags = "";
        if (strpos($tmpl, '$tags') !== false) { // * stabs dynamically typed languages with a rusty spoon *
            $tags = $this->get_tag_list();
            $tags = str_replace("/", "", $tags);
            $tags = preg_replace("/^\.+/", "", $tags);
        }

        $base_href = $config->get_string('base_href');
        $fname = $this->get_filename();
        $base_fname = strpos($fname, '.') ? substr($fname, 0, strrpos($fname, '.')) : $fname;

        $tmpl = str_replace('$id', $this->id, $tmpl);
        $tmpl = str_replace('$hash_ab', substr($this->hash, 0, 2), $tmpl);
        $tmpl = str_replace('$hash_cd', substr($this->hash, 2, 2), $tmpl);
        $tmpl = str_replace('$hash', $this->hash, $tmpl);
        $tmpl = str_replace('$tags', $_escape($tags), $tmpl);
        $tmpl = str_replace('$base', $base_href, $tmpl);
        $tmpl = str_replace('$ext', $this->ext, $tmpl);
        $tmpl = str_replace('$size', "{$this->width}x{$this->height}", $tmpl);
        $tmpl = str_replace('$filesize', to_shorthand_int($this->filesize), $tmpl);
        $tmpl = str_replace('$filename', $_escape($base_fname), $tmpl);
        $tmpl = str_replace('$title', $_escape($config->get_string("title")), $tmpl);
        $tmpl = str_replace('$date', $_escape(autodate($this->posted, false)), $tmpl);

        // nothing seems to use this, sending the event out to 50 exts is a lot of overhead
        if (!SPEED_HAX) {
            $plte = new ParseLinkTemplateEvent($tmpl, $this);
            send_event($plte);
            $tmpl = $plte->link;
        }

        static $flexihash = null;
        static $fh_last_opts = null;
        $matches = [];
        if (preg_match("/(.*){(.*)}(.*)/", $tmpl, $matches)) {
            $pre = $matches[1];
            $opts = $matches[2];
            $post = $matches[3];

            if ($opts != $fh_last_opts) {
                $fh_last_opts = $opts;
                $flexihash = new Flexihash\Flexihash();
                foreach (explode(",", $opts) as $opt) {
                    $parts = explode("=", $opt);
                    $parts_count = count($parts);
                    $opt_val = "";
                    $opt_weight = 0;
                    if ($parts_count === 2) {
                        $opt_val = $parts[0];
                        $opt_weight = $parts[1];
                    } elseif ($parts_count === 1) {
                        $opt_val = $parts[0];
                        $opt_weight = 1;
                    }
                    $flexihash->addTarget($opt_val, $opt_weight);
                }
            }

            // $choice = $flexihash->lookup($pre.$post);
            $choices = $flexihash->lookupList($this->hash, $n+1);  // hash doesn't change
            $choice = $choices[$n];
            $tmpl = $pre.$choice.$post;
        }

        return $tmpl;
    }

    /**
     * #param string[] $terms
     */
    private static function build_search_querylet(array $terms): Querylet
    {
        global $database;

        $tag_querylets = [];
        $img_querylets = [];
        $positive_tag_count = 0;
        $negative_tag_count = 0;

        /*
         * Turn a bunch of strings into a bunch of TagQuerylet
         * and ImgQuerylet objects
         */
        $stpe = new SearchTermParseEvent(null, $terms);
        send_event($stpe);
        if ($stpe->is_querylet_set()) {
            foreach ($stpe->get_querylets() as $querylet) {
                $img_querylets[] = new ImgQuerylet($querylet, true);
            }
        }

        foreach ($terms as $term) {
            $positive = true;
            if (is_string($term) && !empty($term) && ($term[0] == '-')) {
                $positive = false;
                $term = substr($term, 1);
            }
            if (strlen($term) === 0) {
                continue;
            }

            $stpe = new SearchTermParseEvent($term, $terms);
            send_event($stpe);
            if ($stpe->is_querylet_set()) {
                foreach ($stpe->get_querylets() as $querylet) {
                    $img_querylets[] = new ImgQuerylet($querylet, $positive);
                }
            } else {
                // if the whole match is wild, skip this;
                // if not, translate into SQL
                if (str_replace("*", "", $term) != "") {
                    $term = str_replace('_', '\_', $term);
                    $term = str_replace('%', '\%', $term);
                    $term = str_replace('*', '%', $term);
                    $tag_querylets[] = new TagQuerylet($term, $positive);
                    if ($positive) {
                        $positive_tag_count++;
                    } else {
                        $negative_tag_count++;
                    }
                }
            }
        }

        /*
         * Turn a bunch of Querylet objects into a base query
         *
         * Must follow the format
         *
         *   SELECT images.*
         *   FROM (...) AS images
         *   WHERE (...)
         *
         * ie, return a set of images.* columns, and end with a WHERE
         */

        // no tags, do a simple search
        if ($positive_tag_count === 0 && $negative_tag_count === 0) {
            $query = new Querylet("
				SELECT images.*
				FROM images
				WHERE 1=1
			");
        }

        // one positive tag (a common case), do an optimised search
        elseif ($positive_tag_count === 1 && $negative_tag_count === 0) {
            # "LIKE" to account for wildcards
            $query = new Querylet($database->scoreql_to_sql("
				SELECT *
				FROM (
					SELECT images.*
					FROM images
					JOIN image_tags ON images.id=image_tags.image_id
					JOIN tags ON image_tags.tag_id=tags.id
					WHERE SCORE_STRNORM(tag) LIKE SCORE_STRNORM(:tag)
					GROUP BY images.id
				) AS images
				WHERE 1=1
			"), ["tag"=>$tag_querylets[0]->tag]);
        }

        // more than one positive tag, or more than zero negative tags
        else {
            if ($database->get_driver_name() === "mysql") {
                $query = Image::build_ugly_search_querylet($tag_querylets);
            } else {
                $query = Image::build_accurate_search_querylet($tag_querylets);
            }
        }

        /*
         * Merge all the image metadata searches into one generic querylet
         * and append to the base querylet with "AND blah"
         */
        if (!empty($img_querylets)) {
            $n = 0;
            $img_sql = "";
            $img_vars = [];
            foreach ($img_querylets as $iq) {
                if ($n++ > 0) {
                    $img_sql .= " AND";
                }
                if (!$iq->positive) {
                    $img_sql .= " NOT";
                }
                $img_sql .= " (" . $iq->qlet->sql . ")";
                $img_vars = array_merge($img_vars, $iq->qlet->variables);
            }
            $query->append_sql(" AND ");
            $query->append(new Querylet($img_sql, $img_vars));
        }

        return $query;
    }

    /**
     * WARNING: this description is no longer accurate, though it does get across
     * the general idea - the actual method has a few extra optimisations
     *
     * "foo bar -baz user=foo" becomes
     *
     * SELECT * FROM images WHERE
     *           images.id IN (SELECT image_id FROM image_tags WHERE tag='foo')
     *   AND     images.id IN (SELECT image_id FROM image_tags WHERE tag='bar')
     *   AND NOT images.id IN (SELECT image_id FROM image_tags WHERE tag='baz')
     *   AND     images.id IN (SELECT id FROM images WHERE owner_name='foo')
     *
     * This is:
     *   A) Incredibly simple:
     *      Each search term maps to a list of image IDs
     *   B) Runs really fast on a good database:
     *      These lists are calculated once, and the set intersection taken
     *   C) Runs really slow on bad databases:
     *      All the subqueries are executed every time for every row in the
     *      images table. Yes, MySQL does suck this much.
     *
     * #param TagQuerylet[] $tag_querylets
     */
    private static function build_accurate_search_querylet(array $tag_querylets): Querylet
    {
        global $database;

        $positive_tag_id_array = [];
        $negative_tag_id_array = [];

        foreach ($tag_querylets as $tq) {
            $tag_ids = $database->get_col(
                $database->scoreql_to_sql("
					SELECT id
					FROM tags
					WHERE SCORE_STRNORM(tag) LIKE SCORE_STRNORM(:tag)
				"),
                ["tag" => $tq->tag]
            );
            if ($tq->positive) {
                $positive_tag_id_array = array_merge($positive_tag_id_array, $tag_ids);
                if (count($tag_ids) == 0) {
                    # one of the positive tags had zero results, therefor there
                    # can be no results; "where 1=0" should shortcut things
                    return new Querylet("
						SELECT images.*
						FROM images
						WHERE 1=0
					");
                }
            } else {
                $negative_tag_id_array = array_merge($negative_tag_id_array, $tag_ids);
            }
        }

        assert($positive_tag_id_array || $negative_tag_id_array, @$_GET['q']);
        $wheres = [];
        if (!empty($positive_tag_id_array)) {
            $positive_tag_id_list = join(', ', $positive_tag_id_array);
            $wheres[] = "tag_id IN ($positive_tag_id_list)";
        }
        if (!empty($negative_tag_id_array)) {
            $negative_tag_id_list = join(', ', $negative_tag_id_array);
            $wheres[] = "tag_id NOT IN ($negative_tag_id_list)";
        }
        $wheres_str = join(" AND ", $wheres);
        return new Querylet("
			SELECT images.*
			FROM images
			WHERE images.id IN (
				SELECT image_id
				FROM image_tags
				WHERE $wheres_str
				GROUP BY image_id
				HAVING COUNT(image_id) >= :search_score
			)
		", ["search_score"=>count($positive_tag_id_array)]);
    }

    /**
     * this function exists because mysql is a turd, see the docs for
     * build_accurate_search_querylet() for a full explanation
     *
     * #param TagQuerylet[] $tag_querylets
     */
    private static function build_ugly_search_querylet(array $tag_querylets): Querylet
    {
        global $database;

        $positive_tag_count = 0;
        foreach ($tag_querylets as $tq) {
            if ($tq->positive) {
                $positive_tag_count++;
            }
        }

        // only negative tags - shortcut to fail
        if ($positive_tag_count == 0) {
            // TODO: This isn't currently implemented.
            // SEE: https://github.com/shish/shimmie2/issues/66
            return new Querylet("
				SELECT images.*
				FROM images
				WHERE 1=0
			");
        }

        // merge all the tag querylets into one generic one
        $sql = "0";
        $terms = [];
        foreach ($tag_querylets as $tq) {
            $sign = $tq->positive ? "+" : "-";
            $sql .= ' '.$sign.' IF(SUM(tag LIKE :tag'.Image::$tag_n.'), 1, 0)';
            $terms['tag'.Image::$tag_n] = $tq->tag;
            Image::$tag_n++;
        }
        $tag_search = new Querylet($sql, $terms);

        $tag_id_array = [];

        foreach ($tag_querylets as $tq) {
            $tag_ids = $database->get_col(
                $database->scoreql_to_sql("
					SELECT id
					FROM tags
					WHERE SCORE_STRNORM(tag) LIKE SCORE_STRNORM(:tag)
				"),
                ["tag" => $tq->tag]
            );
            $tag_id_array = array_merge($tag_id_array, $tag_ids);

            if ($tq->positive && count($tag_ids) == 0) {
                # one of the positive tags had zero results, therefor there
                # can be no results; "where 1=0" should shortcut things
                return new Querylet("
					SELECT images.*
					FROM images
					WHERE 1=0
				");
            }
        }

        Image::$tag_n = 0;
        return new Querylet('
			SELECT *
			FROM (
				SELECT images.*, ('.$tag_search->sql.') AS score
				FROM images
				LEFT JOIN image_tags ON image_tags.image_id = images.id
				JOIN tags ON image_tags.tag_id = tags.id
				WHERE tags.id IN (' . join(', ', $tag_id_array) . ')
				GROUP BY images.id
				HAVING score = :score
			) AS images
			WHERE 1=1
		', array_merge(
            $tag_search->variables,
            ["score"=>$positive_tag_count]
        ));
    }
}
