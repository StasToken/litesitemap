<?php

namespace stastoken\litesitemap\Controller;

use stastoken\litesitemap\Controller\DomMakerSitemap;
use stastoken\litesitemap\Exceptions\LiteSitemapException;
use stastoken\litesitemap\Exceptions\ModelException;
use stastoken\litesitemap\Model\RulesNode;

class LazySitemap
{
    const SITEMAP_URLS = 'SITEMAP_URLS';
    const SITEMAP_INDEX = 'SITEMAP_URLS';

    private $dir = '';
    private $limit_link = 50;
    private $limit_size = 50000;
    private $chunk_name_make = NULL;
    private $name = 'sitemap.xml';
    private $file_list = [];
    private $temp_files = [];

    /**
     *
     * @param string $dir - the absolute path to save the site map
     * @param string $name - The name of the site map is by default sitemap.xml
     * @param int $limit - Maximum number of links per file 50000
     * @param callable|null $chunk_name_function - function for forming multiple maps 1-sitemap.xml 2-sitemap.xml etc.
     */
    public function __construct(string $dir, string $name = '', int $limit = 50000, callable $chunk_name_function = null)
    {
        $this->dir = $dir;
        $this->name = $name ?: $this->name;
        $this->limit = $limit;
        if (is_null($chunk_name_function)) {
            $this->chunk_name_make = $this->defaultChunk();
        } else {
            $this->chunk_name_make = $chunk_name_function;
        }
    }

    public function make(array $urls, array $rules = [], DomMakerSitemap $dom_maker_redefined = null)
    {
        $number = 1;
//        $last_chunk_name = '';
//        $is_first = true;
        $this->validate($rules);
        $make = function (array &$urls) use (&$make, &$rules,&$is_first, &$number, &$last_chunk_name,&$dom_maker_redefined) {
            if(is_null($dom_maker_redefined)){
                $dom = new DomMakerSitemap();
            }else{
                $dom = clone $dom_maker_redefined;
            }
            foreach ($urls as $ptr => $url) {
                $dom->set($url,$rules);
                if ($dom->bytes() >= $this->limit_size or $dom->links() > $this->limit_link) {
                    $dom->rollback();
//                    if ($is_first) {
//                        $is_first = false;
//                        $this->tempSaving($dom->make(), $this->name);
//                        $make($urls);
//                        return;
//                    }
//                    $chunk_name_function = $this->chunk_name_make;
//                    $chunk_name = $chunk_name_function($number, $this->name, $last_chunk_name);
                    $number++;
//                    $last_chunk_name = $chunk_name;
                    $this->tempSaving($dom->make(), $number,self::SITEMAP_URLS);
                    $make($urls);
                    return;
                }
                unset($urls[$ptr]);
            }
//            if ($is_first) {
//                $is_first = false;
//                $this->tempSaving($dom->make(), $this->name);
//                return;
//            }
//            $chunk_name_function = $this->chunk_name_make;
//            $chunk_name = $chunk_name_function($number, $this->name, $last_chunk_name);
            $number++;
//            $last_chunk_name = $chunk_name;
            $this->tempSaving($dom->make(), $number,self::SITEMAP_URLS);
        };
        $make($urls);
        $this->makeIndexSitemap();



        //todo validate
        $this->save();

    }

    public function makeOld(array $urls, array $rules = [], DomMakerSitemap $dom_maker_redefined = null)
    {
        $number = 1;
        $last_chunk_name = '';
        $is_first = true;
        $this->validate($rules);
        $make = function (array &$urls) use (&$make, &$rules,&$is_first, &$number, &$last_chunk_name,&$dom_maker_redefined) {
            if(is_null($dom_maker_redefined)){
                $dom = new DomMakerSitemap();
            }else{
                $dom = clone $dom_maker_redefined;
            }
            foreach ($urls as $ptr => $url) {
                $dom->set($url,$rules);
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
        foreach ($rules as $key => $rule){
            if(!is_a($rule,RulesNode::class)){
                throw new LiteSitemapException('LiteSitemap::make() arg 2 must be an array of objects "'.RulesNode::class.'" transmitted: "'.gettype($rule) . '" in position: '.$key.'.');
            }
        }
    }

    private function makeIndexSitemap()
    {
        $make_index_sitemap = function (array &$files) use (&$make_index_sitemap, &$rules,&$number,&$dom_maker_redefined) {
            if(is_null($dom_maker_redefined)){
                $dom = new DomMakerSitemapIndex();
            }else{
                $dom = clone $dom_maker_redefined;
            }
            foreach ($files as $ptr => $url) {
                $dom->set($url,$rules);
                if ($dom->bytes() >= $this->limit_size or $dom->links() > $this->limit_link) {
                    $dom->rollback();
                    $this->tempSaving($dom->make(), $number,self::SITEMAP_INDEX);
                    $make_index_sitemap($files);
                    return;
                }
                unset($files[$ptr]);
            }
            $number++;
            $this->tempSaving($dom->make(), $number,self::SITEMAP_INDEX);
        };
        if(count($this->file_list) > 1){
            foreach ($this->file_list as $sitemap){

            }
        }else{
            //If we have one single file, then that's what we'll call it
            $this->file_list[0]->original_name = $this->name;
        }
        return;
    }

    /**
     * The function saves the data to a temporary
     * folder for further verification
     * @param string $xml
     * @param int $number
     * @param int $type
     * @throws LiteSitemapException
     */
    private function tempSaving(string $xml, int $number,string $type)
    {
        $temp_file = tempnam(sys_get_temp_dir(), 'sitemap_generator_'.$number);
        $this->temp_files[]=$temp_file;
        $handle = fopen($temp_file, "a");
        flock($handle, LOCK_EX);
        if (flock($handle, LOCK_EX)) {
            ftruncate($handle, 0);
            fwrite($handle, $xml);
            fflush($handle);
            flock($handle, LOCK_UN);
        }else{
            throw new LiteSitemapException('It was not possible to get an exclusive lock on writing the file: "'.$temp_file.'", the file may be damaged in further work');
        }
        fclose($handle);
        $pre_save = new \stdClass();
        $pre_save->temp_file = $temp_file;
        $pre_save->serial_number = $number;
        $pre_save->original_name = '';
        $pre_save->type = $type;
        $this->file_list[] = $pre_save;
    }

    /**
     * Performs the final transfer of files to the root directory
     * @throws LiteSitemapException
     */
    private function save()
    {
        foreach ($this->file_list as $value){
            $full_path = $this->dir.$value->original_name;
            if(file_exists($full_path)){
                unlink($full_path);
            }
            if (!rename($value->temp_file, $value->original_name)) {
                throw new LiteSitemapException('Failed to transfer file from: '.$value->temp_file.' to:'.$value->original_name);
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
        return function (int $number, string $name, string $lastname): string {
            return $number . '-' . $name;
        };
    }

    /**
     * We will remove all the garbage behind us
     */
    public function __destruct()
    {
        foreach ($this->temp_files as $value){
            if(file_exists($value)) {
                unlink($value);
            }
        }
    }
}