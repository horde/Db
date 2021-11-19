<?php

class InnocentJointable1 extends Horde_Db_Migration_Base
{
    public function up()
    {
        $t = $this->createTable('users_reminders', array('autoincrementKey' => false));
            $t->column('reminder_id', 'integer');
            $t->column('user_id',     'integer');
        $t->end();
    }

    public function down()
    {
        $this->dropTable('users_reminders');
    }
}