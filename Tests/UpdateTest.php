<?php

namespace Panada\Medoo\Tests;

use Panada\Medoo;

class UpdateTest extends Connection
{
    public function testUpdate()
    {
        $name = time();
        
        self::$db->update('account',
            ['user_name' => $name],
            ['email' => 'joe@gmail.com']
        );
        
        $user = self::$db->get('account', 'user_name', [
            'email' => 'joe@gmail.com'
        ]);
        
        $this->assertEquals($user, $name);
    }
}