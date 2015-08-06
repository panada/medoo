<?php

namespace Panada\Medoo\Tests;

use Panada\Medoo;

class SQLTest extends \PHPUnit_Extensions_Database_TestCase
{
    private static $pdo = null;
    private static $db = null;
    private $conn = null;
    
    public function getConnection()
    {
        if ($this->conn === null) {
            
            if (self::$pdo == null) {
                
                self::$db = new Medoo\Medoo([
                    'databaseType' => 'sqlite',
                    'databaseFile' => ':memory:'
                ]);
                
                self::$pdo = self::$db->pdo;
                
                $this->createTable();
            }
            $this->conn = $this->createDefaultDBConnection(self::$pdo, ':memory:');
        }

        return $this->conn;
    }
    
    public function createTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `users` (
            `id` int(11) NOT NULL,
            `name` varchar(50) NOT NULL,
            `email` varchar(50) NOT NULL,
            `created` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (`id`)
          )';
          
        self::$db->pdo->exec($sql);
    }
    
    protected function getDataSet()
    {
        return $this->createArrayDataSet([
            'users' => [
                ['id' => 1, 'email' => 'joe@gmail.com', 'name' => 'joe', 'created' => '2010-04-24 17:15:23'],
                ['id' => 2, 'email' => 'mark@gmail.com',   'name' => 'mark',  'created' => '2010-04-26 12:14:20'],
            ],
        ]);
    }
    
    public function testInsert()
    {
        $this->getConnection();
        
        $lastInsertId = self::$pdo->lastInsertId();
        
        $name = time();
        
        $userId = self::$db->insert('users', [
            'id' => $lastInsertId + 1,
            'name' => $name,
            'email' => $name.'@bar.com',
        ]);
        
        $this->assertEquals($lastInsertId, $userId - 1);
    }
    
    public function testSelectAll()
    {
        $this->getConnection();
        
        $users = self::$db->select('users', '*')->fetchAll(\PDO::FETCH_ASSOC);
        
        $this->assertGreaterThanOrEqual(2, count($users));
    }
}