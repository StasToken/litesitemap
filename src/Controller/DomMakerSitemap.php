<?php

namespace stastoken\litesitemap\Controller;

use DateTimeInterface;
use Exception;
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
     * @param string|null $xml_header - doctype of the document
     * @param string|null $xmlns - Reference to the standard
     * @param DateTimeInterface|null $lastmod_default - lastmod tag default
     * @param float|null $priority_default - priority tag default
     * @param string|null $changefreq_default - changefreq tag default
     * @throws Exception
     */
    public function __construct(
        string            $xml_header = null,
        string            $xmlns = null,
        DateTimeInterface $lastmod_default = null,
        float             $priority_default = null,
        string            $changefreq_default = null
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

    /**
     * Fills the DOM tree
     * @param string $link
     * @param array $rules_node
     * @return $this
     */
    public function set(string $link, array &$rules_node = [])
    {
        $tag_url = $this->root->addChild('url');
        $tag_url->addChild('loc', $link);

        if(count($rules_node) === 0){
            if (!is_null($this->lastmod_default)) $tag_url->addChild('lastmod', $this->lastmod_default->format(\DateTimeInterface::W3C));
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
                $lastmod_value = is_null($lastmod_value) ? $this->lastmod_default->format(\DateTimeInterface::W3C) : $lastmod_value;
                if (!is_null($lastmod_value)) $tag_url->addChild('lastmod', $lastmod_value);
                $priority_value = is_null($priority_value) ? $this->priority_default : $priority_value;
                if (!is_null($priority_value)) $tag_url->addChild('priority', $priority_value);
                $changefreq_value = is_null($changefreq_value) ? $this->changefreq_default : $changefreq_value;
                if (!is_null($changefreq_value)) $tag_url->addChild('changefreq', $changefreq_value);
            } else {
                if (!is_null($this->lastmod_default)) $tag_url->addChild('lastmod', $this->lastmod_default->format(\DateTimeInterface::W3C));
                if (!is_null($this->priority_default)) $tag_url->addChild('priority', $this->priority_default);
                if (!is_null($this->changefreq_default)) $tag_url->addChild('changefreq', $this->changefreq_default);
            }
        }
    }
}