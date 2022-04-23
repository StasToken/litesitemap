<?php

namespace stastoken\litesitemap\Controller;

use stastoken\litesitemap\Controller\DomMakerSitemap;
use stastoken\litesitemap\Exceptions\LiteSitemapException;
use stastoken\litesitemap\Exceptions\ModelException;
use stastoken\litesitemap\Model\RulesNode;


class LazySitemap
{
    private $dir = '';
    private $limit_link = 2;
    private $limit_size = 50000;
    private $chunk_name_make = NULL;
    private $name = '';
    private $link_domain = '';
    private $dom_maker_redefined;
    private $dom_maker_index_redefined;
    private $number_files = 0;
    private $file_sitemap = [];
    private $file_index = [];
    private $temp_files = [];

    /**
     *
     * @param string $dir - The absolute path to save the site map
     * @param string $link_domain - The absolute path to save the site map
     * @param string $name - The name of the site map is by default sitemap.xml
     * @param int $limit - Maximum number of links per file 50000
     * @param \stastoken\litesitemap\Controller\DomMakerSitemap|null $dom_maker_redefined
     * @param DomMakerSitemapIndex|null $dom_maker_index_redefined
     * @param callable|null $chunk_name_function - function for forming multiple maps 1-sitemap.xml 2-sitemap.xml etc.
     */
    public function __construct(
        string               $dir,
        string               $link_domain,
        string               $name = 'sitemap.xml',
        int                  $limit = 50000,
        DomMakerSitemap      $dom_maker_redefined = null,
        DomMakerSitemapIndex $dom_maker_index_redefined = null,
        callable             $chunk_name_function = null
    )
    {
        $this->dir = $dir;
        $this->name = $name ?: $this->name;
        $this->limit = $limit;
        $this->link_domain = $link_domain;
        if (is_null($dom_maker_redefined))
            $this->dom_maker_redefined = new DomMakerSitemap(); else $this->dom_maker_redefined = clone $dom_maker_redefined;
        if (is_null($dom_maker_index_redefined))
            $this->dom_maker_index_redefined = new DomMakerSitemapIndex(); else $this->dom_maker_index_redefined = clone $dom_maker_index_redefined;
        if (is_null($chunk_name_function))
            $this->chunk_name_make = $this->defaultChunk(); else $this->chunk_name_make = $chunk_name_function;

    }

    public function make(array $urls, array $rules = [])
    {
        $this->buildLinks($urls, $rules);
        $this->toNameSitemap();
        $this->buildIndex($rules);
        $this->toNameIndex();
        $this->save();

    }

    private function buildLinks(array $urls, array $rules = [])
    {
        $make = function (array &$urls) use (&$make, &$rules) {
            $dom = new DomMakerSitemap();
            foreach ($urls as $ptr => $url) {
                $dom->set($url, $rules);
                if ($dom->bytes() >= $this->limit_size or $dom->links() > $this->limit_link) {
                    $dom->rollback();
                    $this->number_files++;
                    $this->tempSaving($dom->make(), $this->number_files);
                    $make($urls);
                    return;
                }
                unset($urls[$ptr]);
            }
            $this->number_files++;
            $this->tempSaving($dom->make(), $this->number_files);
        };
        $make($urls);
    }

    private function buildIndex(array $rules = [])
    {
        $sitemap_list = array_merge(array(), $this->file_sitemap); // Just cloning an array
        $blank = function ($number, $original_name) {
            $pre_save = new \stdClass();
            $pre_save->temp_file = '';
            $pre_save->serial_number = $number;
            $pre_save->original_name = $original_name ?: '';
            return $pre_save;
        };

        $make_index_sitemap = function (array &$files) use (&$make_index_sitemap, &$rules, &$blank) {
            $chunk_name_function = &$this->chunk_name_make;
            $dom = new DomMakerSitemapIndex();
            foreach ($files as $ptr => $name) {
                $url = $this->link_domain . $name->original_name;
                $dom->set($url, $rules);
                if ($dom->bytes() >= $this->limit_size or $dom->links() >= $this->limit_link) {
                    $dom->rollback();
                    $this->number_files++;
                    $chunk_name = $chunk_name_function($this->number_files, $this->name);
                    unset($files[$ptr]);
                    $this->tempSaving($dom->make(), $this->number_files, $chunk_name);
                    $files[] = $blank($this->number_files, $chunk_name);

                    $make_index_sitemap($files);
                    return;
                }
                unset($files[$ptr]);
            }
            $this->number_files++;
            $chunk_name = $chunk_name_function($this->number_files, $this->name);
            $this->tempSaving($dom->make(), $this->number_files, $chunk_name);
            $files[] = $blank($this->number_files, $chunk_name);
        };
        $make_index_sitemap($sitemap_list);
    }

    private function toNameSitemap()
    {
        if (count($this->file_sitemap) === 1) { // If the site map fits into one file
            $first = array_key_first($this->file_sitemap);
            $this->file_sitemap[$first]->original_name = $this->name;
        } else {
            $chunk_name_function = &$this->chunk_name_make;
            foreach ($this->file_sitemap as $key => $value) {
                $chunk_name = $chunk_name_function($value->serial_number, $this->name);
                $this->file_sitemap[$key]->original_name = $chunk_name;
            }
        }
    }

    private function toNameIndex()
    {
        //The last generated file must have the original name
        $last_key = array_key_last($this->file_sitemap);
        $this->file_sitemap[$last_key]->original_name = $this->name;
    }

    /**
     * Checks the validity of the passed arguments of the function "make"
     * @param array $rules
     * @throws LiteSitemapException
     */
    public function validate(array $rules)
    {
        foreach ($rules as $key => $rule) {
            if (!is_a($rule, RulesNode::class)) {
                throw new LiteSitemapException('LiteSitemap::make() arg 2 must be an array of objects "' . RulesNode::class . '" transmitted: "' . gettype($rule) . '" in position: ' . $key . '.');
            }
        }
    }


    private function tempSaving(string $xml, int $number, $original_name = null)
    {
        $temp_file = tempnam(sys_get_temp_dir(), 'sitemap_generator_' . $number);
        $this->temp_files[] = $temp_file;
        $handle = fopen($temp_file, "a");
        flock($handle, LOCK_EX);
        if (flock($handle, LOCK_EX)) {
            ftruncate($handle, 0);
            fwrite($handle, $xml);
            fflush($handle);
            flock($handle, LOCK_UN);
        } else {
            throw new LiteSitemapException('It was not possible to get an exclusive lock on writing the file: "' . $temp_file . '", the file may be damaged in further work');
        }
        fclose($handle);
        $pre_save = new \stdClass();
        $pre_save->temp_file = $temp_file;
        $pre_save->serial_number = $number;
        $pre_save->original_name = $original_name ?: '';
        $this->file_sitemap[] = $pre_save;
    }

    /**
     * Performs the final transfer of files to the root directory
     * @throws LiteSitemapException
     */
    private function save()
    {
        foreach ($this->file_sitemap as $value) {
            $full_path = $this->dir . $value->original_name;
            if (file_exists($full_path)) {
                unlink($full_path);
            }
            if (!rename($value->temp_file, $value->original_name)) {
                throw new LiteSitemapException('Failed to transfer file from: ' . $value->temp_file . ' to:' . $value->original_name);
            }
        }
    }


    /**
     * Sets the default function for getting chunk names
     * @return callable
     */
    private function defaultChunk(): callable
    {
        /**
         * The function returns file names when forming parts - can be redefined in the constructor
         */
        return function (int $number, string $name): string {
            return $number . '-' . $name;
        };
    }

    /**
     * We will remove all the garbage behind us
     */
    public function __destruct()
    {
        foreach ($this->temp_files as $value) {
            if (file_exists($value)) {
                unlink($value);
            }
        }
    }
}
 