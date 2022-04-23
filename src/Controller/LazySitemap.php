<?php

namespace stastoken\litesitemap\Controller;

use stastoken\litesitemap\Controller\DomMakerSitemap;
use stastoken\litesitemap\Exceptions\LiteSitemapException;
use stastoken\litesitemap\Exceptions\ModelException;
use stastoken\litesitemap\Model\RulesNode;

class LazySitemap
{
    const SITEMAP_URLS = 'SITEMAP_URLS';
    const SITEMAP_INDEX = 'SITEMAP_INDEX';

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
//        var_dump($this->file_sitemap);

        //todo validate
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

        $blank = function ($number,$original_name){
            $pre_save = new \stdClass();
            $pre_save->temp_file = '';
            $pre_save->serial_number = $number;
            $pre_save->original_name = $original_name ?: '';
            return $pre_save;
        };

$s = 0;

        $make_index_sitemap = function (array &$files) use (&$make_index_sitemap, &$rules,&$blank,&$s) {
            $s++;
            echo "callable #: ".$s."\n";
            $chunk_name_function = &$this->chunk_name_make;
            $dom = new DomMakerSitemapIndex();
            foreach ($files as $ptr => $name) {
                var_dump($ptr,count($files));
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
            $this->tempSaving($dom->make(), $this->number_files,$chunk_name);
            $files[] = $blank($this->number_files, $chunk_name);
        };




//        $make_index_sitemap = function (array &$files) use (&$make_index_sitemap, &$rules) {
//            $chunk_name_function = &$this->chunk_name_make;
//            $dom = new DomMakerSitemapIndex();
//            foreach ($files as $ptr => $name) {
//                $url = $this->link_domain . $name->original_name;
//                $dom->set($url, $rules);
//                if ($dom->bytes() >= $this->limit_size or $dom->links() >= $this->limit_link) {
//                    $dom->rollback();
//                    $this->number_files++;
//                    $chunk_name = $chunk_name_function($this->number_files, $this->name);
//                    $this->tempSaving($dom->make(), $this->number_files, $chunk_name);
//                    $make_index_sitemap($files);
//                    return;
//                }
//                unset($files[$ptr]);
//            }
//            $this->number_files++;
//            $chunk_name = $chunk_name_function($this->number_files, $this->name);
//            $this->tempSaving($dom->make(), $this->number_files,$chunk_name);
//        };
        var_dump($sitemap_list,'==============================');
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
        if (count($this->file_index) === 1) {
            $first = array_key_first($this->file_index);
            $this->file_index[$first]->original_name = $this->name;
        } else {
            $chunk_name_function = &$this->chunk_name_make;
            foreach ($this->file_index as $key => $value) {
                if (array_key_first($this->file_index) === $key) {
                    $this->file_index[$key] = $this->name;
                    continue;
                }
                $chunk_name = $chunk_name_function($value->serial_number, $this->name);
                $this->file_index[$key]->original_name = $chunk_name;
            }
        }
    }

    public function makeOld(array $urls, array $rules = [], DomMakerSitemap $dom_maker_redefined = null)
    {
        $number = 1;
        $last_chunk_name = '';
        $is_first = true;
        $this->validate($rules);
        $make = function (array &$urls) use (&$make, &$rules, &$is_first, &$number, &$last_chunk_name, &$dom_maker_redefined) {
            if (is_null($dom_maker_redefined)) {
                $dom = new DomMakerSitemap();
            } else {
                $dom = clone $dom_maker_redefined;
            }
            foreach ($urls as $ptr => $url) {
                $dom->set($url, $rules);
                if ($dom->bytes() >= $this->limit_size or $dom->links() > $this->limit_link) {
                    $dom->rollback();
                    if ($is_first) {
                        $is_first = false;
                        $this->tempSaving($dom->make(), $this->name);
                        $make($urls);
                        return;
                    }
                    $chunk_name_function = $this->chunk_name_make;
                    $chunk_name = $chunk_name_function($number, $this->name, $last_chunk_name);
                    $number++;
                    $last_chunk_name = $chunk_name;
                    $this->tempSaving($dom->make(), $chunk_name);
                    $make($urls);
                    return;
                }
                unset($urls[$ptr]);
            }
            if ($is_first) {
                $is_first = false;
                $this->tempSaving($dom->make(), $this->name);
                return;
            }
            $chunk_name_function = $this->chunk_name_make;
            $chunk_name = $chunk_name_function($number, $this->name, $last_chunk_name);
            $number++;
            $last_chunk_name = $chunk_name;
            $this->tempSaving($dom->make(), $chunk_name);
        };
        $make($urls);
        $this->makeIndexSitemap();


        //todo validate
        $this->save();

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
//        var_dump($temp_file);
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
//        $pre_save->type = $type;
        $this->file_sitemap[] = $pre_save;

//        if($type === self::SITEMAP_URLS){
//            $this->file_sitemap[] = $pre_save;
//        }else{
//            $this->file_index[] = $pre_save;
//        }
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
 