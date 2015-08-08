<?php

namespace Panada\Medoo\Tests;

use Panada\Medoo;

class SelectTest extends Connection
{
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
    
    public function testRelativityCondition()
    {
        self::$db->select('account', 'user_name', [
            'email' => 'foo@bar.com'
        ]);
        
        $sql = 'SELECT "user_name" FROM "account" WHERE "email" = \'foo@bar.com\'';
        
        $this->assertEquals($sql, self::$db->lastQuery());
        
        // [Basic]
        self::$db->select("account", "user_name", [
            "AND" => [
                "user_id[>]" => 200,
                "age[<>]" => [18, 25],
                "gender" => "female"
            ]
        ]);
        
        $sql = 'SELECT "user_name" FROM "account" WHERE "user_id" > 200 AND ("age" BETWEEN 18 AND 25) AND "gender" = \'female\'';
        
        $this->assertEquals($sql, self::$db->lastQuery());
         
        self::$db->select("account", "user_name", [
            "OR" => [
                "user_id[>]" => 200,
                "age[<>]" => [18, 25],
                "gender" => "female"
            ]
        ]);
        
        $sql = 'SELECT "user_name" FROM "account" WHERE "user_id" > 200 OR ("age" BETWEEN 18 AND 25) OR "gender" = \'female\'';
        
        $this->assertEquals($sql, self::$db->lastQuery());
         
        // [Compound]
        self::$db->has("account", [
            "AND" => [
                "OR" => [
                    "user_name" => "foo",
                    "email" => "foo@bar.com"
                ],
                "password" => "12345"
            ]
        ]);
        
        $sql = 'SELECT EXISTS(SELECT 1 FROM "account" WHERE ("user_name" = \'foo\' OR "email" = \'foo@bar.com\') AND "password" = \'12345\')';
        
        $this->assertEquals($sql, self::$db->lastQuery());
         
        // [IMPORTANT]
        // Because Medoo is using array data construction to describe relativity condition,
        // array with duplicated key will be overwritten.
        //
        // This will be error:
        self::$db->select("account", '*', [
            "AND" => [
                "OR" => [
                    "user_name" => "foo",
                    "email" => "foo@bar.com"
                ],
                "OR" => [
                    "user_name" => "bar",
                    "email" => "bar@foo.com"
                ]
            ]
        ]);
        
        $sql = 'SELECT * FROM "account" WHERE ("user_name" = \'bar\' OR "email" = \'bar@foo.com\')';
        
        $this->assertEquals($sql, self::$db->lastQuery());
         
        // To correct that, just assign a comment for each AND and OR key name. The comment content can be everything.
        self::$db->select("account", '*', [
            "AND #Actually, this comment feature can be used on every AND and OR relativity condition" => [
                "OR #the first condition" => [
                    "user_name" => "foo",
                    "email" => "foo@bar.com"
                ],
                "OR #the second condition" => [
                    "user_name" => "bar",
                    "email" => "bar@foo.com"
                ]
            ]
        ]);
        
        $sql = 'SELECT * FROM "account" WHERE (("user_name" = \'foo\' OR "email" = \'foo@bar.com\') AND ("user_name" = \'bar\' OR "email" = \'bar@foo.com\'))';
        
        $this->assertEquals($sql, self::$db->lastQuery());
    }
}