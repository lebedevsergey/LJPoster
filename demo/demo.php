<?php
// Copyright 2017 Sergey Lebedev
// Licensed under the Apache License, Version 2.0

require_once __DIR__.'/../src/LJPoster.php';
use LJPoster\LJPoster;

$login = 'YOUR_LIVEJOURNAL_LOGIN';
$password = 'YOUR_LIVEJOURNAL_PASSWORD';

$c = new LJPoster($login, $password);

// you can create posts
$post1 = $c->createPost('test1', 'test1', \DateTime::createFromFormat('j-M-Y', '17-Feb-2022'), ['tag1', 'tag2']);
$post2 = $c->createPost('test2', 'test2', \DateTime::createFromFormat('j-M-Y', '17-Feb-2022'), ['tag1', 'tag2'], ["opt_nocomments" => true, "opt_preformatted" => true]);

// edit posts
$c->editPost($post2['itemid'], 'test2_changed', 'test2_changed', \DateTime::createFromFormat('j-M-Y', '18-Feb-2022'), ['tag3', 'tag4']);

// get posts
// by item id
print_r($c->getPostById($post2['itemid']));
// by date
print_r($c->getPostsForDate());
// get number of last posts
print_r($c->getLastNPosts(2));

// delete posts
$c->deletePost($post1['itemid']);
$c->deletePost($post2['itemid']);