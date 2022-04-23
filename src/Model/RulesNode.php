<?php

namespace stastoken\litesitemap\Model;

use DateTimeInterface;
use ReflectionClass;
use stastoken\litesitemap\Exceptions\ModelException;
/**
 * The model contains rules that must be applied
 * to each processed URL that corresponds to a regular expression
 */
class RulesNode
{
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

    //features
    private $rules_find = NULL; // regular expression for pattern search
    private $lastmod = NULL;    // the value of the lastmod tag for this rule
    private $priority = NULL;   // the value of the priority tag for this rule
    private $changefreq = NULL; // the value of the changefreq tag for this rule

    /**
     * Regular expression for link search
     * @param string $regex
     * @return $this
     */
    public function setRules(string $regex):self
    {
        $this->rules_find = $regex;
        return $this;
    }

    /**
     * The time to set in the tag lastmod
     * @param DateTimeInterface $lastmod
     * @return $this
     */
    public function setLastmod(DateTimeInterface $lastmod):self
    {
        $this->lastmod = $lastmod;
        return $this;
    }

    /**
     * Priority to be set in the tag priority
     * @param float $priority
     * @return $this
     * @throws ModelException
     */
    public function setPriority(float $priority):self
    {
        if($priority > 1 or $priority < 0.1){
            throw new ModelException('The priority property must be from 0.1 to 1.0 passed: "'.$priority.'".');
        }
        $this->priority = $priority;
        return $this;
    }

    /**
     * The frequency of updating the content to be set in the tag changefreq
     * @param string $changefreq
     * @return $this
     * @throws ModelException
     */
    public function setChangefreq(string $changefreq):self
    {
        $allowed = self::getConstants();
        foreach ($allowed as $value){
            if($changefreq === $value){
                $this->changefreq = $changefreq;
                return $this;
            }
        }
        throw new ModelException('The priority "changefreq" must have one of the values: ['.implode(',',$allowed).'] transmitted: "'.$changefreq.'".');
    }

    /**
     * Returns a string with a regular expression to check
     * @return string
     */
    public function getRules():string
    {
        return $this->rules_find;
    }

    /**
     * Returns a string with the content update time
     * @link https://www.w3.org/TR/NOTE-datetime
     * @return DateTimeInterface|null
     */
    public function getLastmod(): ?DateTimeInterface
    {
        return $this->lastmod;
    }

    /**
     * Returns a string with priority
     * @return float|null
     */
    public function getPriority(): ?float
    {
        return $this->priority;
    }

    /**
     * Returns a string with the refresh rate
     * @return string|null
     */
    public function getChangefreq(): ?string
    {
        return $this->changefreq;
    }

    /**
     * Returns a list of constants used in the class
     * @return array
     */
    static private function getConstants():array
    {
        $o_class = new ReflectionClass(__CLASS__);
        return $o_class->getConstants();
    }
}