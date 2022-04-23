<?php

namespace stastoken\litesitemap\Controller;

use stastoken\litesitemap\Model\RulesNode;

/**
 * The class forms a DOM tree, and returns a ready XML document
 */
class DomMakerSitemap extends DomMaker
{
    private $xml_header = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
    private $xmlns = 'http://www.sitemaps.org/schemas/sitemap/0.9';
    protected $root;
    protected $lastmod_default = null;
    protected $priority_default = null;
    protected $changefreq_default = null;


    /**
     * Initialize the class to create a DOM tree
     * Lastmod,Priority,Changefreq - will be applied to all tags by
     * default if there is no special rule for them or will be skipped
     * if they are of type null
     * @param null $xml_header - doctype of the document
     * @param null $xmlns - Reference to the standard
     * @param string|null $lastmod_default - lastmod tag default
     * @param string|null $priority_default - priority tag default
     * @param string|null $changefreq_default - changefreq tag default
     * @throws \Exception
     */
    public function __construct(
        $xml_header = null,
        $xmlns = null,
        string $lastmod_default = null,
        string $priority_default = null,
        string $changefreq_default = null
    )
    {
        $xml_header = $xml_header ?? $this->xml_header;
        $xmlns = $xmlns ?? $this->xmlns;
        $this->root = new \SimpleXMLElement($xml_header . '<urlset></urlset>');
        $this->root->addAttribute("xmlns", $xmlns);
        $this->lastmod_default = $lastmod_default;
        $this->priority_default = $priority_default;
        $this->changefreq_default = $changefreq_default;
    }

//    /**
//     * Returns the finished XML document as a string
//     * @return string
//     */
//    public function make():string
//    {
//        return $this->root->asXML();
//    }

    /**
     * Fills the DOM tree
     * @param string $link
     * @param array $rules_node
     * @return $this|bool
     */
    public function set(string $link, array &$rules_node = [])
    {
        $tag_url = $this->root->addChild('url');
        $tag_url->addChild('loc', $link);

        if(count($rules_node) === 0){
            if (!is_null($this->lastmod_default)) $tag_url->addChild('lastmod', $this->lastmod_default);
            if (!is_null($this->priority_default)) $tag_url->addChild('priority', $this->priority_default);
            if (!is_null($this->changefreq_default)) $tag_url->addChild('changefreq', $this->changefreq_default);
            return $this;
        }
        /**@var RulesNode $rules * */
        foreach ($rules_node as $rules) {
            if ($this->scan($rules->getRules(),$link)) { // there is a special handler for this url
                $lastmod_value = $rules->getLastmod();
                $priority_value = $rules->getPriority();
                $changefreq_value = $rules->getChangefreq();
                /*
                 * We set optional tags only if there is a
                 * value from the filter or by default
                 */
                $lastmod_value = is_null($lastmod_value) ? $this->lastmod_default : $lastmod_value;
                if (!is_null($lastmod_value)) $tag_url->addChild('lastmod', $lastmod_value);
                $priority_value = is_null($priority_value) ? $this->priority_default : $priority_value;
                if (!is_null($priority_value)) $tag_url->addChild('priority', $priority_value);
                $changefreq_value = is_null($changefreq_value) ? $this->changefreq_default : $changefreq_value;
                if (!is_null($changefreq_value)) $tag_url->addChild('changefreq', $changefreq_value);
            } else {
                if (!is_null($this->lastmod_default)) $tag_url->addChild('lastmod', $this->lastmod_default);
                if (!is_null($this->priority_default)) $tag_url->addChild('priority', $this->priority_default);
                if (!is_null($this->changefreq_default)) $tag_url->addChild('changefreq', $this->changefreq_default);
            }
        }
    }

//    /**
//     * Rolls back the last added link from the tree
//     */
//    public function rollback()
//    {
//        $index = $this->root->count() - 1;
//        unset($this->root->url[$index]);
//    }
//
//    /**
//     * Returns the number of links in the tree
//     * @return int
//     */
//    public function links():int
//    {
//        return $this->root->count();
//    }
//
//    /**
//     * Returns the size of the text occupied by the dom tree
//     * @return int
//     */
//    public function bytes():int
//    {
//        return mb_strlen($this->root->asXML());
//    }
//
//    /**
//     * Сhecks whether the rule matches the current URL
//     * @param string $regex
//     * @param string $link
//     * @return bool
//     */
//    private function scan(string $regex,string $link):bool
//    {
//        $is_find = preg_match($regex, $link, $matches, PREG_OFFSET_CAPTURE, 0);
//        if($is_find === 1) return true;
//        return false;
//    }
}