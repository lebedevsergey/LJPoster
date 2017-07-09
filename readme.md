# LJPoster #

### What is it? ###
* A simple PHP class for working with LiveJournal posts through API
* Requires PHP 5.6 or higher
* Supports:
  * Posts reading
  * Post creating
  * Post edit
  * Post deleting    

### How to use it? ###
* See demo app in ./demo folder

```php
// create new LJPoster instance with your LiveJournal login and password
$c = new LJPoster($login, $password);

// you can create posts
$post1 = $c->createPost('test1', 'test1', \DateTime::createFromFormat('j-M-Y', '17-Feb-2022'), ['tag1', 'tag2']);

// edit posts
$c->editPost($post1['itemid'], 'test1_changed', 'test1_changed');

// delete posts
$c->deletePost($post1['itemid']);

// get posts
// by item id
print_r($c->getPostById($res['itemid']));
// by date
print_r($c->getPostsForDate());
// by number of last posts
print_r($c->getLastNPosts(2));
```

