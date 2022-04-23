<?php

namespace stastoken\litesitemap\Controller;

use Exception;
use stastoken\litesitemap\Controller\DomMakerSitemap;
use stastoken\litesitemap\Exceptions\LiteSitemapException;
use stastoken\litesitemap\Exceptions\ModelException;
use stastoken\litesitemap\Model\Config;
use stastoken\litesitemap\Model\RulesNode;


class LiteSitemap
{

    private $number_files = 0;
    private $file_sitemap = [];
    private $temp_files = [];

    private $config;

    /**
     * Initializes the site map generator
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Generates a site map based on the provided links and rules
     * @param array $urls - Array of links
     * @param RulesNode[] $rules - Array of rules
     * @throws LiteSitemapException
     */
    public function make(array $urls, array $rules = [])
    {
        $this->validate($rules,$urls);
        $this->buildLinks($urls, $rules);
        $this->toNameSitemap();
        $this->buildIndex($rules);
        $this->toNameIndex();
        $this->save();
    }

    /**
     * Generates site maps based on links by applying the passed rules to each link
     * @param array $urls
     * @param array $rules
     * @throws LiteSitemapException
     */
    private function buildLinks(array $urls, array $rules = [])
    {
        /**
         * @throws LiteSitemapException
         * @throws Exception
         */
        $make = function (array &$urls) use (&$make, &$rules) {
            $dom = new DomMakerSitemap(
                $this->config->getXmlHeaderSitemap(),
                $this->config->getXmlnsSitemap(),
                $this->config->getSitemapLastmodDefault(),
                $this->config->getSitemapPriorityDefault(),
                $this->config->getSitemapChangefreqDefault()
            );
            foreach ($urls as $ptr => $url) {
                $dom->set($url, $rules);
                if ($dom->bytes() >= $this->config->getLimitSize() or $dom->links() > $this->config->getLimitLink()) {
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

    /**
     * Generates a map for a list of site maps (when we have a lot of files)
     * @param array $rules
     * @throws LiteSitemapException
     * @throws Exception
     */
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
            $chunk_name_function = $this->config->getChunkNameMake();
            $dom = new DomMakerSitemapIndex(
                $this->config->getXmlHeaderSitemapIndex(),
                $this->config->getXmlnsSitemapIndex(),
                $this->config->getSitemapIndexLastmodDefault()
            );
            foreach ($files as $ptr => $name) {
                $url = $this->config->getLinkDomain() . $name->original_name;
                $dom->set($url, $rules);
                if ($dom->bytes() >= $this->config->getLimitSize() or $dom->links() >= $this->config->getLimitLink()) {
                    $dom->rollback();
                    $this->number_files++;
                    $chunk_name = $chunk_name_function($this->number_files, $this->config->getName());
                    unset($files[$ptr]);
                    $this->tempSaving($dom->make(), $this->number_files, $chunk_name);
                    $files[] = $blank($this->number_files, $chunk_name);

                    $make_index_sitemap($files);
                    return;
                }
                unset($files[$ptr]);
            }
            $this->number_files++;
            $chunk_name = $chunk_name_function($this->number_files, $this->config->getName());
            $this->tempSaving($dom->make(), $this->number_files, $chunk_name);
            $files[] = $blank($this->number_files, $chunk_name);
        };
        $make_index_sitemap($sitemap_list);
    }

    /**
     * Will assign names to file with site maps
     */
    private function toNameSitemap()
    {
        if (count($this->file_sitemap) === 1) { // If the site map fits into one file
            $first = array_key_first($this->file_sitemap);
            $this->file_sitemap[$first]->original_name = $this->config->getName();
        } else {
            $chunk_name_function = $this->config->getChunkNameMake();
            foreach ($this->file_sitemap as $key => $value) {
                $chunk_name = $chunk_name_function($value->serial_number, $this->config->getName());
                $this->file_sitemap[$key]->original_name = $chunk_name;
            }
        }
    }

    /**
     * Names index files
     */
    private function toNameIndex()
    {
        //The last generated file must have the original name
        $last_key = array_key_last($this->file_sitemap);
        $this->file_sitemap[$last_key]->original_name = $this->config->getName();
    }

    /**
     * Checks the validity of the passed arguments of the function "make"
     * @param array $rules
     * @param array $links
     * @throws LiteSitemapException
     */
    public function validate(array $rules,array $links)
    {
        foreach ($rules as $key => $rule) {
            if (!is_a($rule, RulesNode::class)) {
                throw new LiteSitemapException('LiteSitemap::make() arg 2 must be an array of objects "' . RulesNode::class . '" transmitted: "' . gettype($rule) . '" in position: ' . $key . '.');
            }
        }
        //todo links
    }

    /**
     * Saves site maps to temporary storage
     * @param string $xml
     * @param int $number
     * @param null $original_name
     * @throws LiteSitemapException
     */
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
            $full_path = $this->config->getDir() . $value->original_name;
            if (file_exists($full_path)) {
                unlink($full_path);
            }
            if (!rename($value->temp_file, $value->original_name)) {
                throw new LiteSitemapException('Failed to transfer file from: ' . $value->temp_file . ' to:' . $value->original_name);
            }
        }
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
 