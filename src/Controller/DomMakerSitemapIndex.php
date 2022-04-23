<?php

namespace stastoken\litesitemap\Controller;

use stastoken\litesitemap\Model\RulesNode;

/**
 *
 */
class DomMakerSitemapIndex extends DomMaker
{
    private $xml_header = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
    private $xmlns = 'http://www.sitemaps.org/schemas/sitemap/0.9';
    protected $root;
    protected $lastmod_default = null;

    /**
     *
     * @param null $xml_header
     * @param null $xmlns
     * @param string|null $lastmod_default
     * @throws \Exception
     */
    public function __construct(
        $xml_header = null,
        $xmlns = null,
        string $lastmod_default = null
    )
    {
        $xml_header = $xml_header ?? $this->xml_header;
        $xmlns = $xmlns ?? $this->xmlns;
        $this->root = new \SimpleXMLElement($xml_header . '<sitemapindex></sitemapindex>');
        $this->root->addAttribute("xmlns", $xmlns);
        $this->lastmod_default = $lastmod_default;
    }

    /**
     * Fills the DOM tree
     * @param string $link
     * @param array $rules_node
     * @return $this
     */
    public function set(string $link, array &$rules_node = [])
    {
        $tag_sitemap = $this->root->addChild('sitemap');
        $tag_sitemap->addChild('loc', $link);

        if(count($rules_node) === 0){
            if (!is_null($this->lastmod_default)) $tag_sitemap->addChild('lastmod', $this->lastmod_default);
            return $this;
        }
        /**@var RulesNode $rules * */
        foreach ($rules_node as $rules) {
            if ($this->scan($rules->getRules(),$link)) { // there is a special handler for this url
                $lastmod_value = $rules->getLastmod();
                /*
                 * We set optional tags only if there is a
                 * value from the filter or by default
                 */
                $lastmod_value = is_null($lastmod_value) ? $this->lastmod_default : $lastmod_value;
                if (!is_null($lastmod_value)) $tag_sitemap->addChild('lastmod', $lastmod_value);
            } else {
                if (!is_null($this->lastmod_default)) $tag_sitemap->addChild('lastmod', $this->lastmod_default);
            }
        }
    }
}