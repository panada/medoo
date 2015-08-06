<?php

namespace Panada\Medoo\Tests;

use Panada\Medoo;

class SQLTest extends \PHPUnit_Extensions_Database_TestCase
{
    private static $db = null;
    private $conn = null;
    
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
            `user_id` int(11) NOT NULL,
            `user_name` varchar(50) NOT NULL,
            `email` varchar(50) NOT NULL,
            `created` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (`user_id`)
          );
          CREATE TABLE IF NOT EXISTS `post` (
            `post_id` int(11) NOT NULL,
            `author_id` int(11) NOT NULL,
            `avatar_id` int(11) NOT NULL,
            PRIMARY KEY (`post_id`)
          );
          CREATE TABLE IF NOT EXISTS `album` (
            `album_id` int(11) NOT NULL,
            `user_id` int(11) NOT NULL,
            PRIMARY KEY (`album_id`)
          );
          CREATE TABLE IF NOT EXISTS `photo` (
            `avatar_id` int(11) NOT NULL,
            PRIMARY KEY (`avatar_id`)
          );';
          
        self::$db->pdo->exec($sql);
    }
    
    protected function getDataSet()
    {
        return $this->createArrayDataSet([
            'account' => [
                ['user_id' => 1, 'email' => 'joe@gmail.com', 'user_name' => 'joe', 'created' => '2010-04-24 17:15:23'],
                ['user_id' => 2, 'email' => 'mark@gmail.com',   'user_name' => 'mark',  'created' => '2010-04-26 12:14:20'],
            ],
        ]);
    }
    
    public function testInsert()
    {
        $this->getConnection();
        
        $lastInsertId = self::$db->pdo->lastInsertId();
        
        $name = time();
        
        $userId = self::$db->insert('account', [
            'user_id' => $lastInsertId + 1,
            'user_name' => $name,
            'email' => $name.'@bar.com',
        ]);
        
        $this->assertEquals($lastInsertId, $userId - 1);
    }
    
    public function testSelectAll()
    {
        $this->getConnection();
        
        $users = self::$db->select('account', '*')->fetchAll(\PDO::FETCH_ASSOC);
        
        $this->assertGreaterThanOrEqual(2, count($users));
    }
}