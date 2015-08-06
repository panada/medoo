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
            `age` int(11) NULL,
            `birthday` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            `city` varchar(20) NULL,
            `promoted` int(5) NULL,
            PRIMARY KEY (`user_id`)
          );
          CREATE TABLE IF NOT EXISTS `post` (
            `post_id` int(11) NOT NULL,
            `author_id` int(11) NOT NULL,
            `avatar_id` int(11) NOT NULL,
            `comments`  int(11) NOT NULL,
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
                ['user_id' => 1, 'email' => 'joe@gmail.com', 'user_name' => 'joe', 'birthday' => '2010-04-24 17:15:23'],
                ['user_id' => 2, 'email' => 'mark@gmail.com',   'user_name' => 'mark',  'birthday' => '2010-04-26 12:14:20'],
            ],
        ]);
    }
    
    public function testInsert()
    {
        $lastInsertId = self::$db->pdo->lastInsertId();
        
        $name = time();
        
        $userId = self::$db->insert('account', [
            'user_id' => $lastInsertId + 1,
            'user_name' => $name,
            'email' => $name.'@bar.com',
        ]);
        
        $this->assertEquals($lastInsertId, $userId - 1);
    }
    
    public function testSelect()
    {
        $users = self::$db->select('account', '*')->fetchAll(\PDO::FETCH_ASSOC);
        
        $this->assertGreaterThanOrEqual(2, count($users));
    }
    
    public function testWhere()
    {
        self::$db->select('account', 'user_name', [
            'email' => 'foo@bar.com'
        ]);
        
        $sql = 'SELECT "user_name" FROM "account" WHERE "email" = \'foo@bar.com\'';
        
        $this->assertEquals($sql, self::$db->lastQuery());
         
        self::$db->select('account', 'user_name', [
            'user_id' => 200
        ]);
        
        $sql = 'SELECT "user_name" FROM "account" WHERE "user_id" = 200';
        
        $this->assertEquals($sql, self::$db->lastQuery());
         
        self::$db->select('account', 'user_name', [
            'user_id[>]' => 200
        ]);
        
        $sql = 'SELECT "user_name" FROM "account" WHERE "user_id" > 200';
        
        $this->assertEquals($sql, self::$db->lastQuery());
         
        self::$db->select('account', 'user_name', [
            'user_id[>=]' => 200
        ]);
        
        $sql = 'SELECT "user_name" FROM "account" WHERE "user_id" >= 200';
        
        $this->assertEquals($sql, self::$db->lastQuery());
         
        self::$db->select('account', 'user_name', [
            'user_id[!]' => 200
        ]);
        
        $sql = 'SELECT "user_name" FROM "account" WHERE "user_id" != 200';
        
        $this->assertEquals($sql, self::$db->lastQuery());
         
        self::$db->select('account', 'user_name', [
            'age[<>]' => [200, 500]
        ]);
        
        $sql = 'SELECT "user_name" FROM "account" WHERE ("age" BETWEEN 200 AND 500)';
        
        $this->assertEquals($sql, self::$db->lastQuery());
         
        self::$db->select('account', 'user_name', [
            'age[><]' => [200, 500]
        ]);
        
        $sql = 'SELECT "user_name" FROM "account" WHERE ("age" NOT BETWEEN 200 AND 500)';
        
        $this->assertEquals($sql, self::$db->lastQuery());
         
        $now = date('Y-m-d');
        
        self::$db->select('account', 'user_name', [
            'birthday[><]' => [date('Y-m-d', mktime(0, 0, 0, 1, 1, 2015)), $now]
        ]);
        
        $sql = 'SELECT "user_name" FROM "account" WHERE ("birthday" NOT BETWEEN \'2015-01-01\' AND \''.$now.'\')';
        
        $this->assertEquals($sql, self::$db->lastQuery());
         
        self::$db->select('account', 'user_name', [
            'OR' => [
                'user_id' => [2, 123, 234, 54],
                'email' => ['foo@bar.com', 'cat@dog.com', 'admin@medoo.in']
            ]
        ]);
        
        $sql = 'SELECT "user_name" FROM "account" WHERE "user_id" IN (2,123,234,54) OR "email" IN (\'foo@bar.com\',\'cat@dog.com\',\'admin@medoo.in\')';
        
        $this->assertEquals($sql, self::$db->lastQuery());
         
        // [Negative condition]
        self::$db->select('account', 'user_name', [
            'AND' => [
                'user_name[!]' => 'foo',
                'user_id[!]' => 1024,
                'email[!]' => ['foo@bar.com', 'cat@dog.com', 'admin@medoo.in'],
                'city[!]' => null,
                'promoted[!]' => true
            ]
        ]);
        
        $sql = 'SELECT "user_name" FROM "account" WHERE "user_name" != \'foo\' AND "user_id" != 1024 AND "email" NOT IN (\'foo@bar.com\',\'cat@dog.com\',\'admin@medoo.in\') AND "city" IS NOT NULL AND "promoted" != 1';
        
        $this->assertEquals($sql, self::$db->lastQuery());
    }
}