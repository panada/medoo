<?php

namespace Panada\Medoo\Tests;

use Panada\Medoo;

class InsertTest extends Connection
{
    public function testInsert()
    {
        $name = time();
        
        $insertId = self::$db->insert('account', [
            'user_name' => $name,
            'email' => $name.'@bar.com',
        ]);
        
        $this->assertGreaterThan(0, $insertId);
    }
    
    public function testSerialization()
    {
        $insertId = self::$db->insert("account", [
            "user_name" => "foo",
            "email" => "foo@bar.com",
            "age" => 25,
            "lang" => ["en", "fr", "jp", "cn"] // => 'a:4:{i:0;s:2:"en";i:1;s:2:"fr";i:2;s:2:"jp";i:3;s:2:"cn";}'
        ]);
        
        $sql = 'INSERT INTO "account" ("user_name", "email", "age", "lang") VALUES (\'foo\', \'foo@bar.com\', \'25\', \'a:4:{i:0;s:2:"en";i:1;s:2:"fr";i:2;s:2:"jp";i:3;s:2:"cn";}\')';
        
        $this->assertEquals($sql, self::$db->lastQuery());
        $this->assertGreaterThan(0, $insertId);
         
        $insertId = self::$db->insert("account", [
            "user_name" => "foo",
            "email" => "foo@bar.com",
            "age" => 25,
            "(JSON) lang" => ["en", "fr", "jp", "cn"] // => '["en","fr","jp","cn"]'
        ]);
        
        $sql = 'INSERT INTO "account" ("user_name", "email", "age", "lang") VALUES (\'foo\', \'foo@bar.com\', \'25\', \'["en","fr","jp","cn"]\')';
        
        $this->assertEquals($sql, self::$db->lastQuery());
        $this->assertGreaterThan(0, $insertId);
    }
    
    public function testMultiInsertion()
    {
        $insertId = self::$db->insert("account", [
            [
                "user_name" => "foo",
                "email" => "foo@bar.com",
                "age" => 25,
                "city" => "New York",
                "(JSON) lang" => ["en", "fr", "jp", "cn"]
            ],
            [
                "user_name" => "bar",
                "email" => "bar@foo.com",
                "age" => 14,
                "city" => "Hong Kong",
                "(JSON) lang" => ["en", "jp", "cn"]
            ]
        ]);
        
        $sql = 'INSERT INTO "account" ("user_name", "email", "age", "city", "lang") VALUES (\'bar\', \'bar@foo.com\', \'14\', \'Hong Kong\', \'["en","jp","cn"]\')';
        
        $this->assertEquals($sql, self::$db->lastQuery());
        $this->assertGreaterThan(0, $insertId);
    }
}