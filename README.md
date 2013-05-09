#f3flux

Bridge between Fat-Free framework (https://github.com/bcosca/fatfree) and FluxBB 1.5.* (https://github.com/fluxbb/fluxbb).

With this bridge you'll be able to authenticate (login, logout, isLogged) users using FluxBB cookies.

You can put a custom login page in your site, the user will be logged in the forum as well.

Or you have an user coming from the forum and you want to identify it -- now you can.

##Remarks:

* Now working with vanilla FluxBB 1.5.*!
* You must have F3. This code has been developed with F3 ver. _3.0.7_.
* MySQL only. This code uses F3's DB\SQL 

##Installing:

1. Determine your F3's AUTOLOAD folder (see https://github.com/bcosca/fatfree#quick-reference).
2. Put the content of this repository inside that folder.
3. Done.

##Usage:

### Domain-wide cookies
If you have your site in a different subdomain than the forum (e.g.: the site is www.gamezoo.it, the forum is forum.gamezoo.it), you need to edit fluxbb's `config.php`:

    $cookie_domain = 'gamezoo.it'; // replace with your domain!
    $cookie_path = '/';

Read more about it here: http://php.net/manual/en/function.setcookie.php

A nice trick: if you're serving the same forum on more than one site (forum.gamezoo.it, forum.gamezoo.org) you can have this instead:
    
    /* since we've got more than one domain... */
    preg_match("/[^\.\/]+\.[^\.\/]+$/", $_SERVER['HTTP_HOST'], $match);
    $cookie_domain = $match[0];
    unset($match);
    $cookie_path = '/';


### User authentication

You only need to pass the constructor the path of your FluxBB installation. The bridge will connect to FluxBB's database and check/set the session cookie.

    // start using F3
    $f3 = require('/path/to/fat-free-framework/lib/base.php');
    
    [...]
    
    // define FluxBB path (don't forget the trailing slash)
    $f3->set('fluxbb_root', '/path/to/FluxBB/')
    
    // create FluxBB bridge
    $bridge =  new FluxBridge\Bridge($f3->get('fluxbb_root'));
    
    // see if the current user is logged in
    $loggedIn = $bridge->isLoggedIn();
    
    //... if he's not logged in, log him in
    if(!$loggedIn)
        if ($bridge->login($username, $password, $remember))
            echo "logged in";
        else echo "wrong user/pass";
    
    //... if he's logged in, log him out
    else
	$bridge->logout();


### Useful objects

After the bridge instantiation you've got a working DB\SQL connection to FluxBB's database:

    // $bridge->db is the db connection
    $bridge->db->exec('SELECT * FROM users LIMIT 10'); // hmmm no table prefix...

Likewise, the  board configuration is stored in the `pun_config` array:

    $cookie_timeout = $bridge->pun_config['o_timeout_visit'];
    
If the user is logged in (after a successful `login()` or `isLoggedIn()`) you've got his properties in an array:

    // $bridge->pun_user is the user array
    $user_id = $bridge->pun_user['id'];
    $username = $bridge->pun_user['username'];

### Using the bridge with GameZoo's FluxBB (featuring PHPass and Akismet, https://github.com/sevendays/fluxbb15-GZ)

Couldn't be simpler.

* Grab PHPass from here: http://www.openwall.com/phpass/
* Put the `PasswordHash.php` file in your f3's autoload folder
* Instantiate the bridge with an additional parameter: `$bridge = new \FluxBridge\Bridge($fluxbb_root, false);`. The second parameter specifies that you're not using vanilla FluxBB.
* Profit.

