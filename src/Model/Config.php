<?php

namespace stastoken\litesitemap\Model;

use DateTimeInterface;
use stastoken\litesitemap\Exceptions\ConfigException;

/**
 * Allows you to override the initial
 * initialization parameters
 */
class Config
{
    const HTTP = 'HTTP';
    const HTTPS = 'HTTPS';
    const DOMAIN_VERIFICATION = "/^http(s)?:\/\//m";

    const DEFAULT_NAME = 'sitemap.xml';
    const DEFAULT_CLEAR_DIR = false;
    const DEFAULT_CLEAR_MASK = '/(([0-9]-)?)sitemap\.xml((\.gz)?)/m';
    const DEFAULT_LIMIT_LINK = 50000;
    const DEFAULT_LIMIT_SIZE = 50000;
    const DEFAULT_PROTOCOL = 'HTTP';
    const DEFAULT_COMPRESSION = false;
    const DEFAULT_VALIDATION = true;
    const DEFAULT_HEADER_SITEMAP = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
    const DEFAULT_XMLNS_SITEMAP = "http://www.sitemaps.org/schemas/sitemap/0.9";
    const DEFAULT_HEADER_SITEMAP_INDEX = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
    const DEFAULT_XMLNS_SITEMAP_INDEX = "http://www.sitemaps.org/schemas/sitemap/0.9";

    /*
     Tag constants "changefreq"
     which are allowed by the standard
     */
    const CHANGE_ALWAYS = 'always';
    const CHANGE_HOURLY = 'hourly';
    const CHANGE_DAILY = 'daily';
    const CHANGE_WEEKLY = 'weekly';
    const CHANGE_MONTHLY = 'monthly';
    const CHANGE_YEARLY = 'yearly';
    const CHANGE_NEVER = 'never';

    /**
     * To check valid values
     * @var string[]
     */
    private $changefreq_validate = [
        self::CHANGE_ALWAYS,
        self::CHANGE_HOURLY,
        self::CHANGE_DAILY,
        self::CHANGE_WEEKLY,
        self::CHANGE_MONTHLY,
        self::CHANGE_YEARLY,
        self::CHANGE_NEVER
    ];
    /**
     * The absolute path to the directory where the site map will be saved
     * @var string
     */
    private $dir;

    /**
     * Full name of the map site
     * Will be used for the main file
     * @var string
     */
    private $name;

    /**
     * Sending to the site for which the map is generated
     * @var string
     */
    private $link_domain;

    /**
     * Do I need to call the directory cleanup function before updating the data
     * If there are fewer maps, this will allow you to remove the second maps
     * @var boolean
     */
    private $clear_dir;

    /**
     * Regular expression for searching for files to clean up
     * @var string
     */
    private $clear_mask;

    /**
     * Maximum number of links per file
     * The standard allows a maximum of 50,000
     * @var int
     */
    private $limit_link;

    /**
     * Maximum file size
     * The standard limits the maximum file size 50Mb (52428800 bytes)
     * The value is specified in bytes
     * @var int
     */
    private $limit_size;

    /**
     * A function that will be called to format file
     * names if there are more than one
     * @var callable
     */
    private $chunk_name_make;

    /**
     * The type of HTTP protocol used on the site must be either "HTTP" or "HTTPS"
     * @var string
     */
    private $protocol;

    /**
     * Should the sitemap be compressed
     * @var boolean
     */
    private $compression;

    /**
     * Do I need to perform an additional check of already
     * created maps before placing them in the directory
     * for publication
     * @var boolean
     */
    private $validation;

    /**
     * XML document declaration string for the site map
     * @var string
     */
    private $xml_header_sitemap = '';

    /**
     * A reference to the definition of a namespace for files containing links
     * @var string
     */
    private $xmlns_sitemap = '';

    /**
     * XML document declaration string for the site map index
     * @var string
     */
    private $xml_header_sitemap_index = '';

    /**
     * A reference to the definition of a namespace for index files containing links
     * @var string
     */
    private $xmlns_sitemap_index = '';

    /**
     * @var DateTimeInterface
     */
    private $sitemap_lastmod_default = null;

    /**
     * @var float
     */
    private $sitemap_priority_default = null;

    /**
     * @var string
     */
    private $sitemap_changefreq_default = null;
    /**
     * @var DateTimeInterface
     */
    private $sitemap_index_lastmod_default = null;

    /**
     * Constructor, sets parameters for silence
     */
    public function __construct()
    {
        $this->dir = getcwd();
        $this->name = self::DEFAULT_NAME;
        $this->clear_dir = self::DEFAULT_CLEAR_DIR;
        $this->clear_mask = self::DEFAULT_CLEAR_MASK;
        $this->limit_link = self::DEFAULT_LIMIT_LINK;
        $this->limit_size = self::DEFAULT_LIMIT_SIZE;
        $this->protocol = self::DEFAULT_PROTOCOL;
        $this->compression = self::DEFAULT_COMPRESSION;
        $this->validation = self::DEFAULT_VALIDATION;
        $this->xml_header_sitemap = self::DEFAULT_HEADER_SITEMAP;
        $this->xmlns_sitemap = self::DEFAULT_XMLNS_SITEMAP;
        $this->xml_header_sitemap_index = self::DEFAULT_HEADER_SITEMAP_INDEX;
        $this->xmlns_sitemap_index = self::DEFAULT_XMLNS_SITEMAP_INDEX;
    }

    /**
     * The absolute path to the directory where the site map will be saved
     * @return string
     */
    public function getDir(): string
    {
        return $this->dir;
    }


    /**
     * The absolute path to the directory where the site map will be saved
     * @param string $dir
     * @return $this
     * @throws ConfigException
     */
    public function setDir(string $dir): Config
    {
        $split = str_split($dir);
        $key_last = array_key_last($split);
        if($split[$key_last] !== DIRECTORY_SEPARATOR){
            $dir .= DIRECTORY_SEPARATOR;
        }
        if (!file_exists($dir)) {
            throw new ConfigException('The save directory does not exist: '.$dir);
        }
        $this->dir = $dir;
        return $this;
    }


    /**
     * Full name of the map site
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * Full name of the map site
     * @param string $name
     * @return $this
     */
    public function setName(string $name): Config
    {
        $this->name = $name;
        return $this;
    }


    /**
     * Sending to the site for which the map is generated
     * @return string
     */
    public function getLinkDomain(): string
    {
        return $this->link_domain;
    }


    /**
     * Sending to the site for which the map is generated
     * @param string $link_domain
     * @return $this
     * @throws ConfigException
     */
    public function setLinkDomain(string $link_domain): Config
    {
        $split = str_split($link_domain);
        $key_last = array_key_last($split);
        if($split[$key_last] !== '/'){
            $link_domain .= DIRECTORY_SEPARATOR;
        }
        $link_domain = mb_strtolower($link_domain);
        $is_find = preg_match(self::DOMAIN_VERIFICATION, $link_domain, $matches, PREG_OFFSET_CAPTURE, 0);
        if ($is_find !== 1){
            throw new ConfigException('The site address was transmitted incorrectly, example: http://example.com/ or https://example.com/ it was transmitted: "'.$link_domain.'"');
        }
        $this->link_domain = $link_domain;
        return $this;
    }


    /**
     * Do I need to call the directory cleanup function before updating the data
     * If there are fewer maps, this will allow you to remove the second maps
     * @return bool
     */
    public function isClearDir(): bool
    {
        return $this->clear_dir;
    }


    /**
     * Do I need to call the directory cleanup function before updating the data
     * If there are fewer maps, this will allow you to remove the second maps
     * @param bool $clear_dir
     * @return $this
     */
    public function setClearDir(bool $clear_dir): Config
    {
        $this->clear_dir = $clear_dir;
        return $this;
    }


    /**
     * Regular expression for searching for files to clean up
     * @return string
     */
    public function getClearMask(): string
    {
        return $this->clear_mask;
    }


    /**
     * Regular expression for searching for files to clean up
     * @param string $clear_mask
     * @return $this
     */
    public function setClearMask(string $clear_mask): Config
    {
        $this->clear_mask = $clear_mask;
        return $this;
    }


    /**
     * Maximum number of links per file
     * The standard allows a maximum of 50,000
     * @return int
     */
    public function getLimitLink(): int
    {
        return $this->limit_link;
    }


    /**
     * Maximum number of links per file
     * The standard allows a maximum of 50,000
     * @param int $limit_link
     * @return $this
     * @throws ConfigException
     */
    public function setLimitLink(int $limit_link): Config
    {
        if($limit_link > self::DEFAULT_LIMIT_LINK){
            throw new ConfigException('The maximum limit "'.self::DEFAULT_LIMIT_LINK.'" of links in a single file exceeds the maximum allowed by the standard');
        }
        $this->limit_link = $limit_link;
        return $this;
    }


    /**
     * Maximum file size
     * The standard limits the maximum file size 50Mb (52428800 bytes)
     * The value is specified in bytes
     * @return int
     */
    public function getLimitSize(): int
    {
        return $this->limit_size;
    }


    /**
     * Maximum file size
     * The standard limits the maximum file size 50Mb (52428800 bytes)
     * The value is specified in bytes
     * @param int $limit_size
     * @return $this
     * @throws ConfigException
     */
    public function setLimitSize(int $limit_size): Config
    {
        if($limit_size > self::DEFAULT_LIMIT_SIZE){
            throw new ConfigException('Maximum file size limit "'.self::DEFAULT_LIMIT_SIZE.'" bytes Allowed by the standard. More transmitted');
        }
        $this->limit_size = $limit_size;
        return $this;
    }


    /**
     * A function that will be called to format file
     * names if there are more than one
     * @return callable
     */
    public function getChunkNameMake():callable
    {
        if(is_null($this->chunk_name_make)) {
            /**
             * Standard file name generation function
             */
            return function (int $number, string $name): string {
                return $number . '-' . $name;
            };
        }
        return $this->chunk_name_make;
    }


    /**
     * A function that will be called to format file
     * names if there are more than one
     * @param callable $chunk_name_make
     * @return $this
     */
    public function setChunkNameMake(callable $chunk_name_make): Config
    {
        $this->chunk_name_make = $chunk_name_make;
        return $this;
    }


    /**
     * The type of HTTP protocol used on the site must be either "HTTP" or "HTTPS"
     * @return string
     */
    public function getProtocol(): string
    {
        return $this->protocol;
    }


    /**
     * The type of HTTP protocol used on the site must be either "HTTP" or "HTTPS"
     * @param string $protocol
     * @return $this
     * @throws ConfigException
     */
    public function setProtocol(string $protocol): Config
    {
        $protocol = mb_strtoupper($protocol);
        if($protocol !== self::HTTP or $protocol !== self::HTTPS){
            throw new ConfigException('The protocol type passed is not correct expected: "'.self::HTTP.'" or "'.self::HTTPS.'"');
        }
        $this->protocol = $protocol;
        return $this;
    }


    /**
     * Should the sitemap be compressed
     * @return boolean
     */
    public function isCompression(): bool
    {
        return $this->compression;
    }


    /**
     * Should the sitemap be compressed
     * @param boolean $compression
     * @return $this
     */
    public function setCompression(bool $compression): Config
    {
        $this->compression = $compression;
        return $this;
    }


    /**
     * Do I need to perform an additional check of already
     * created maps before placing them in the directory
     * for publication
     * @return boolean
     */
    public function isValidation(): bool
    {
        return $this->validation;
    }


    /**
     * Do I need to perform an additional check of already
     * created maps before placing them in the directory
     * for publication
     * @param boolean $validation
     * @return $this
     */
    public function setValidation(bool $validation): Config
    {
        $this->validation = $validation;
        return $this;
    }


    /**
     * XML document declaration string for the site map
     * @return string
     */
    public function getXmlHeaderSitemap(): string
    {
        return $this->xml_header_sitemap;
    }


    /**
     * XML document declaration string for the site map
     * @param string $xml_header_sitemap
     * @return $this
     */
    public function setXmlHeaderSitemap(string $xml_header_sitemap): Config
    {
        $this->xml_header_sitemap = $xml_header_sitemap;
        return $this;
    }


    /**
     * A reference to the definition of a namespace for files containing links
     * @return string
     */
    public function getXmlnsSitemap(): string
    {
        return $this->xmlns_sitemap;
    }


    /**
     * A reference to the definition of a namespace for files containing links
     * @param string $xmlns_sitemap
     * @return $this
     */
    public function setXmlnsSitemap(string $xmlns_sitemap): Config
    {
        $this->xmlns_sitemap = $xmlns_sitemap;
        return $this;
    }


    /**
     * XML document declaration string for the site map index
     * @return string
     */
    public function getXmlHeaderSitemapIndex(): string
    {
        return $this->xml_header_sitemap_index;
    }


    /**
     * XML document declaration string for the site map index
     * @param string $xml_header_sitemap_index
     * @return $this
     */
    public function setXmlHeaderSitemapIndex(string $xml_header_sitemap_index): Config
    {
        $this->xml_header_sitemap_index = $xml_header_sitemap_index;
        return $this;
    }


    /**
     * A reference to the definition of a namespace for index files containing links
     * @return string
     */
    public function getXmlnsSitemapIndex(): string
    {
        return $this->xmlns_sitemap_index;
    }


    /**
     * A reference to the definition of a namespace for index files containing links
     * @param string $xmlns_sitemap_index
     * @return $this
     */
    public function setXmlnsSitemapIndex(string $xmlns_sitemap_index): Config
    {
        $this->xmlns_sitemap_index = $xmlns_sitemap_index;
        return $this;
    }


    /**
     * @return DateTimeInterface
     */
    public function getSitemapLastmodDefault(): ?DateTimeInterface
    {
        return $this->sitemap_lastmod_default;
    }

    /**
     * @param DateTimeInterface $sitemap_lastmod_default
     * @return Config
     */
    public function setSitemapLastmodDefault(DateTimeInterface $sitemap_lastmod_default): Config
    {
        $this->sitemap_lastmod_default = $sitemap_lastmod_default;
        return $this;
    }

    /**
     * @return float
     */
    public function getSitemapPriorityDefault(): ?float
    {
        return $this->sitemap_priority_default;
    }

    /**
     * @param float $sitemap_priority_default
     * @return Config
     * @throws ConfigException
     */
    public function setSitemapPriorityDefault(float $sitemap_priority_default): Config
    {
        if($sitemap_priority_default > 1 or $sitemap_priority_default < 0.1){
            throw new ConfigException('The priority property must be from 0.1 to 1.0 passed: "'.$sitemap_priority_default.'".');
        }
        $this->sitemap_priority_default = $sitemap_priority_default;
        return $this;
    }

    /**
     * @return string
     */
    public function getSitemapChangefreqDefault(): ?string
    {
        return $this->sitemap_changefreq_default;
    }

    /**
     * @param string $sitemap_changefreq_default
     * @return Config
     * @throws ConfigException
     */
    public function setSitemapChangefreqDefault(string $sitemap_changefreq_default): Config
    {

        if(!in_array($sitemap_changefreq_default,$this->changefreq_validate)){
            throw new ConfigException('The priority "changefreq" must have one of the values: ['.implode(',',$this->changefreq_validate).'] transmitted: "'.$sitemap_changefreq_default.'".');
        }
        $this->sitemap_changefreq_default = $sitemap_changefreq_default;
        return $this;
    }

    /**
     * @return DateTimeInterface
     */
    public function getSitemapIndexLastmodDefault(): ?DateTimeInterface
    {
        return $this->sitemap_index_lastmod_default;
    }

    /**
     * @param DateTimeInterface $sitemap_index_lastmod_default
     * @return Config
     */
    public function setSitemapIndexLastmodDefault(DateTimeInterface $sitemap_index_lastmod_default): Config
    {
        $this->sitemap_index_lastmod_default = $sitemap_index_lastmod_default;
        return $this;
    }

    /**
     * Sets the HTTP protocol
     * It's just a convenient alias for the function setProtocol - just sugar
     * @return $this
     */
    public function setHttp(): Config
    {
        $this->protocol = self::HTTP;
        return $this;
    }

    /**
     * Sets the HTTPS protocol
     * It's just a convenient alias for the function setProtocol - just sugar
     * @return $this
     */
    public function setHttps(): Config
    {
        $this->protocol = self::HTTPS;
        return $this;
    }

    /**
     * Do I need to call the directory cleanup function before updating the data
     * If there are fewer maps, this will allow you to remove the second maps
     * It's just a convenient alias for the function setClearDir - just sugar
     * @return $this
     */
    public function clearDir(): Config
    {
        $this->clear_dir = true;
        return $this;
    }

    /**
     * Do I need to perform an additional check of already
     * created maps before placing them in the directory
     * It's just a convenient alias for the function setValidation - just sugar
     * @return $this
     */
    public function validation(): Config
    {
        $this->validation = true;
        return $this;
    }

    /**
     * Should the sitemap be compressed
     * It's just a convenient alias for the function setCompression - just sugar
     * @return $this
     */
    public function compress(): Config
    {
        $this->compression = true;
        return $this;
    }
}