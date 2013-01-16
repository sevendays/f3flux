#f3flux

Bridge between Fat-Free framework (https://github.com/bcosca/fatfree) and GameZoo FluxBB 1.5.* (https://github.com/sevendays/fluxbb15-GZ).

With this bridge you'll be able to authenticate (login, logout, isLogged) users using FluxBB cookies.

You can put a custom login page in your site, the user will be logged in the forum as well.

Or you have an user coming from the forum and you want to identify it -- now you can.

##Remarks:

* This code does __not__ work with vanilla FluxBB 1.5.*  because it has been designed for GameZoo's implementation (using PHPass for passwords instead of SHA1). Feel free to fork this repository and edit the code to make it work with vanilla FluxBB.
* You must have F3. This code has been developed with F3 ver. _3.0.2_.
* MySQL only. This code uses F3's DB\SQL 

##Installing:

1. Determine your F3's AUTOLOAD folder (see https://github.com/bcosca/fatfree#quick-reference).
2. Put the content of this repository inside that folder.
3. Done.

##Usage:

### Domain-wide cookies
If you have your site in a different subdomain than the forum (e.g.: the site is www.gamezoo.it, the forum is forum.gamezoo.it), you need to edit fluxbb's config.php:

    $cookie_domain = 'gamezoo.it'; // replace with your domain!
    $cookie_path = '/';

Read more about it here: http://php.net/manual/en/function.setcookie.php

### Bridge creation

You only need to pass the constructor the path of your FluxBB installation.


    // start using F3
    $f3 = require('/path/to/fat-free-framework/lib/base.php');
    
    [...]
    
    // define FluxBB path (don't forget the trailing slash)
    $f3->set('fluxbb_root', '/path/to/FluxBB/')
    
    // create FluxBB bridge as global object
    //  the class is being autoloaded by F3!
    $f3->set('FluxBB', new FluxBridge\Bridge($f3->get('fluxbb_root')));

The Bridge constructor will:
* read config.php
* connect to the database with FluxBB's credentials
* read the forum configuration ($pun_config in FluxBB's code)

### Reading FluxBB config

The config is managed through the class __FluxBridge\Cfg__.

Cookie/DB variables in fluxbb/config.php are stored in two arrays:

    $cookie_config = $f3->get('FluxBB')->cfg->cookie;
    $db_config = $f3->get('FluxBB')->cfg->db;

The board configuration is stored in the pun_config array:

    $pun_config = $f3->get('FluxBB')->cfg->pun_config;

### Authenticating users

The class __FluxBridge\Auth__ has the methods to:
* check if the user is logged in
* login the user
* logout the user

Example:

    // check if the user is logged in
    $logged_in = $f3->get('FluxBB')->auth->isLoggedIn();
    
    if($logged_in)
    {
        // log the user out
        $f3->get('FluxBB')->auth->logout();
    }
    else
    {
        // log the user in
        // $form_user and $form_password come from the login form
        $f3->get('FluxBB')->auth->login($form_username, $form_password);
        
        // this sets the cookie for a longer time than the one defined in $pun_config['o_timeout_visit']
        //$f3->get('FluxBB')->auth->login($form_username, $form_password, TRUE);
    }

### Reading current user ($pun_user)

While the user is logged in you can read the user properties:

    $pun_user = $f3->get('FluxBB')->auth->pun_user;

This is equivalent to FluxBB pun_user variable: it holds the user's row found in FluxBB's 'users' table.
    
