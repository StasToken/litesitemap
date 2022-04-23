<?php

namespace stastoken\litesitemap\Controller;

abstract class DomMaker
{
    /**
     * Fills the DOM tree
     * @param string $link
     * @param array $rules_node
     * @return $this
     */
    abstract public function set(string $link, array &$rules_node = []);

    /**
     * Returns the finished XML document as a string
     * @return string
     */
    public function make(): string
    {
        return $this->root->asXML();
    }

    /**
     * Rolls back the last added link from the tree
     */
    public function rollback()
    {
        $index = $this->root->count() - 1;
        unset($this->root->url[$index]);
    }

    /**
     * Returns the number of links in the tree
     * @return int
     */
    public function links(): int
    {
        return $this->root->count();
    }

    /**
     * Returns the size of the text occupied by the dom tree
     * @return int
     */
    public function bytes(): int
    {
        return mb_strlen($this->root->asXML());
    }

    /**
     * Checks whether the rule matches the current URL
     * @param string $regex
     * @param string $link
     * @return bool
     */
    protected function scan(string $regex, string $link): bool
    {
        $is_find = preg_match($regex, $link, $matches, PREG_OFFSET_CAPTURE, 0);
        if ($is_find === 1) return true;
        return false;
    }
}