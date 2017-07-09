<?php
// Copyright 2017 Sergey Lebedev
// Licensed under the Apache License, Version 2.0

require_once __DIR__.'/../src/LJPoster.php';
use LJPoster\LJPoster;

$login = 'YOUR_LIVEJOURNAL_LOGIN';
$password = 'YOUR_LIVEJOURNAL_PASSWORD';

$c = new LJPoster($login, $password);

$post1 = $c->createPost('test1', 'test1', \DateTime::createFromFormat('j-M-Y', '17-Feb-2022'), ['tag1', 'tag2']);
$post2 = $c->createPost('test2', 'test2', \DateTime::createFromFormat('j-M-Y', '17-Feb-2022'), ['tag1', 'tag2'], ["opt_nocomments" => true, "opt_preformatted" => true]);

$c->editPost($post2['itemid'], 'test2_changed', 'test2_changed', \DateTime::createFromFormat('j-M-Y', '18-Feb-2022'), ['tag3', 'tag4']);

print_r($c->getPostById($post2['itemid']));
print_r($c->getPostsForDate());
print_r($c->getLastNPosts(2));

$c->deletePost($post1['itemid']);
$c->deletePost($post2['itemid']);