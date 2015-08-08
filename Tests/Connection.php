<?php

namespace Panada\Medoo\Tests;

use Panada\Medoo;

class Connection extends \PHPUnit_Extensions_Database_TestCase
{
    protected static $db = null;
    protected $conn = null;
    
    public function getConnection()
    {
        if ($this->conn === null) {
            
            if (self::$db == null) {
                
                self::$db = new Medoo\Medoo([
                    'databaseType' => 'sqlite',
                    'databaseFile' => ':memory:'
                ]);
                
                $this->createTable();
            }
            $this->conn = $this->createDefaultDBConnection(self::$db->pdo, ':memory:');
        }

        return $this->conn;
    }
    
    public function createTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `account` (
            `user_id` int(11),
            `user_name` varchar(50) NOT NULL,
            `email` varchar(50) NOT NULL,
            `age` int(11) NULL,
            `birthday` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            `city` varchar(20) NULL,
            `promoted` int(5) NULL,
            `lang` varchar(50) NULL,
            PRIMARY KEY (`user_id`)
          );
          CREATE TABLE IF NOT EXISTS `post` (
            `post_id` int(11),
            `author_id` int(11) NOT NULL,
            `avatar_id` int(11) NOT NULL,
            `comments`  int(11) NOT NULL,
            PRIMARY KEY (`post_id`)
          );
          CREATE TABLE IF NOT EXISTS `album` (
            `album_id` int(11),
            `user_id` int(11) NOT NULL,
            PRIMARY KEY (`album_id`)
          );
          CREATE TABLE IF NOT EXISTS `photo` (
            `avatar_id` int(11),
            PRIMARY KEY (`avatar_id`)
          );';
          
        self::$db->pdo->exec($sql);
    }
    
    protected function getDataSet()
    {
        return $this->createArrayDataSet([
            'account' => [
                ['email' => 'joe@gmail.com', 'user_name' => 'joe', 'birthday' => '2010-04-24 17:15:23'],
                ['email' => 'mark@gmail.com',   'user_name' => 'mark',  'birthday' => '2010-04-26 12:14:20'],
            ],
        ]);
    }
}