<?php

require __DIR__ . '/vendor/autoload.php';

use stastoken\litesitemap\Controller\LazySitemap;
use stastoken\litesitemap\Model\RulesNode;

$urls = [
    'https://example.com',
    'https://example.com/page',
    'https://example.com/about',
    'https://example.com/about1',
    'https://example.com/about2',
];

$rule_one = new RulesNode();
$rule_one->setRules('/https:\/\/example\.com\/about[0-9]$/');
$rule_one->setLastmod(new \DateTime());
$rule_one->setPriority(0.1);
$rule_one->setChangefreq(RulesNode::CHANGE_HOURLY);
$rules = [$rule_one];

$ls = new LazySitemap('./','http://example.com/');
$ls->make($urls,$rules);

