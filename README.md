# yotsubanome +
credits sparky4 orginal owner of yotubanome.
maki updating the yotsubanome code and adding more features to it.

#How to install yotubanome

PHP >= 5.2.x (PHP 7 have not tested it yet)

cd into the directory you wanted to be at

example: cd /var/www/html git clone https://github.com/maki64/yotsubanome/

install mysql after that create the database

   ```
    
    $ mysql -u root -p
    mysql > CREATE USER 'root'@'localhost' IDENTIFIED BY 'password';
    mysql > CREATE DATABASE imageboard;
    mysql > GRANT ALL PRIVILEGES ON imageboard . * TO ‘root’@'localhost';
    mysql > FLUSH PRIVILEGES;
    ```
    
    after go to config.php
    
    define("CONNECTION_STRING", 'mysql://imageboard:root@localhost/yotsubanome/img_loog/'); // line 140 edit put this
    
    # privileges to directorys 
    
    chmod 777 
    777 - /cache/
    777 - /src/
    777 - /thumb/
    
    #installation is ready
    
    hit yotubanome.php 
    
    you should be ready to go!
