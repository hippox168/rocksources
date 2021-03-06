<?php
require_once 'PHPUnit/Framework.php';

require_once '../DatabaseRow.php';

class DatabaseRowWithoutSchemaTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var dsn
     */
    //protected $dsn = 'pgsql:host=localhost port=5432 dbname=testdb user=dbrow password=dbrow';
    protected $dsn = 'sqlite::memory:';
    //protected $dsn = 'sqlite:rowtest.sq3';

    /**
     * @var db
     */
    protected $db;

    /**
     * @var    DatabaseRow
     * @access protected
     */
    protected $object;
    protected $table;

    /**
     * @var mock data
     * @access protected
     */
    protected $mockData = array(
        'id'  => 6,
        'name'    => 'admin',
        'password' => 'QWE',    // without Schema, this filed will not be encoded.
        'permission'    => 0,
        'email'     => 'admin@abc.com'
    );

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     *
     * @access protected
     */
    protected function setUp()
    {
        $this->mockData = (object)$this->mockData;

        try {
            $this->db = new PDO($this->dsn);
        }
        catch (PDOException $e) {
            $this->fail('Connect database error! ' . $e->getMessage());
        }
        
        $this->table = 'Database_RowTest';

        $params->db = $this->db;
        $params->table = $this->table;
        $this->object = new DatabaseRow($params);
        /* the other way is:
        $this->object = new DatabaseRow(array(
            'db'        => $this->db,
            'schema'    => new Schema('Schema_TestSchema.js'),
            'table'     => $this->table
        ));
        */

        $this->db->query('CREATE TABLE "'.$this->table.'" (
            id integer primary key unique,
            name varchar(40) not null,
            password varchar(40) not null,
            permission int default 0,
            email varchar(255)
        );');
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     *
     * @access protected
     */
    protected function tearDown()
    {
    }


    /**
     * Clean: Set data as empty
     *
     * 根據 schema 的預設值清除資料中的欄位內容。
     * id default is true. It means this field could be ignored, DBMS will assign a value.
     * name default is false. It means this filed must to assign a value, else will error.
     * password default is false.
     * permission default is 0.
     * @see Schema_TestSchema.js
     */
    public function testClean() {
        $this->object->clean();
        $this->assertEquals(true, $this->object->id);
        $this->assertEquals(false, $this->object->name);
        $this->assertEquals(false, $this->object->password);
        $this->assertEquals(0, $this->object->permission);

        $this->assertEquals(true, $this->object['id']);
        $this->assertEquals(false, $this->object['name']);
        $this->assertEquals(false, $this->object['password']);
        $this->assertEquals(0, $this->object['permission']);

    }

    /**
     * Assign invalid data
     * Test whether the role of pattern.
     *
     * When you assign values to fields, DatabaseRow will apply the pattern
     * role to check them.
     */
    public function testAssignInvalidData() {
        //直接用一個空陣列作為資料來源指派給資料列各欄。
        //assign() 會將無效欄位的名稱儲放在第二個參數 $invalidFields 中回傳。
        $r = $this->object->assign(array(), $invalidFields);

        //沒有全部通過檢查，查看第二個參數的內容。
        $this->assertFalse($r);

        //id, permission 有指定預設值，可以接受。
        $this->assertContains('name', $invalidFields);
        $this->assertContains('password', $invalidFields);

        //name, password 沒有指定預設值，故資料來源必須有值，此資料來源無效。
        $this->assertNotContains('id', $invalidFields);
        $this->assertNotContains('permission', $invalidFields);
    }

    /**
     * Assign data
     * Test whether the role of pattern.
     */
    public function testAssign() {
        //mockData 是預先準備好的測試用資料值。內容皆合schema要求。
        $this->assertTrue($this->object->assign($this->mockData));
    }

    /**
     * Test table() to get table name of this row.
     */
    public function testTable() {
        $this->assertEquals($this->table, $this->object->table());
    }


    /**
     * Test fieldList() to get a list of fields' name.
     */
    public function testFieldList() {
        $this->assertEquals(array('id', 'name', 'password', 'permission', 'email'),
            $this->object->fieldList());
    }

    /**
     * Test to get primary key.
     */
    public function testPrimaryKey() {
        $this->assertEquals('id', $this->object->primaryKey());
    }

    /**
     * Test the values in data are valid.
     *
     * Before you assign() with $data, you could invoke isValid() to check
     * all field's content are valid.
     */
    public function testIsValid() {
        $this->assertTrue($this->object->isValid($this->mockData));

        $invalidData = $this->mockData;
        $invalidData->id = 'abc.com';
        //id的pattern是 ctype_digit, 故此值無效。
        $this->assertFalse($this->object->isValid($invalidData));

    }

    public function testUnsetFieldWithDefault() {
        unset($this->object->email);
    }

    public function testUnsetFieldWithDefault2() {
        unset($this->object['email']);
    }

    /**
     * Test insert
     */
    public function testCrud() {
        $this->object->assign($this->mockData);

        $this->assertTrue(($this->object->insert() ? true : false));
        
        $newPermission = 16;

        $this->object->permission = $newPermission;
        $this->object->email = 'xxx@xxx';
        $this->assertTrue(($this->object->update() ? true : false));

        $id = $this->mockData->id;
        $data = $this->object->get($id);

        $this->assertFalse( empty($data) );
        $this->assertEquals($this->mockData->name, $data->name);
        $this->assertEquals('****', $data->password);
        $this->assertEquals($newPermission, $data->permission);

        $this->assertTrue($this->object->delete());
        $data = $this->object->get($this->mockData->id);
        $this->assertTrue(empty($data));
    }

    /**
     * Test get
     */
    public function testCrud2() {
        $data = $this->mockData;

        $data->id += 1;

        $this->object->assign($data);

        $this->object->insert();
        //$this->object->get();

        $this->assertEquals($data->name, $this->object['name']);
        $this->assertEquals($data->permission, $this->object->permission);

        //If you don't invoke get() after insert() or update(),
        //the data will still be raw data, not encoded.
        //So, that assert should be NotEquals
        $this->assertNotEquals('****', $this->object->password);


        $this->object->permission = 20;
        $this->object->name = 'RROOCCKK';

        $this->object->update();
        $result = $this->object->get();

        $this->assertEquals('RROOCCKK', $result->name);
        $this->assertEquals('RROOCCKK', $this->object->name);

        $this->assertEquals(20, $result->permission);
        $this->assertEquals(20, $this->object->permission);

        $this->assertTrue( $this->object->delete() );

        $result = $this->object->get($data->id);
        $this->assertTrue(empty($result));
    }

    /**
     * Password 使用非對稱編碼。解碼動作並不會還原其值。
     * 更新時，如果沒有改變 password，那麼 update 動作不應對已經編碼過的內容再次
     * 編碼。
     */
    public function testGetRawDataAndUpdatePassword() {
        $data = $this->mockData;

        $this->object->assign($data);
        
        $encodedPassword = $this->object->schema()->password->encode($data->password);

        $this->object->insert();
        $this->assertEquals($data->password, $this->object['password']);

        $this->object->get(); //reload data from database.
        $this->assertEquals('****', $this->object['password']);

        $this->object->getRawData(); //reload raw data from database.
        $this->assertEquals($encodedPassword, $this->object['password']);


        $this->object['permission'] = 99;

        $this->object->update();
        $this->assertEquals(
            'UPDATE "Database_RowTest" SET "permission" = \'99\' WHERE "id" = \'6\';',
            $this->object->getLastQueryString()
        ); //only change permission field.

        $this->object->getRawData(); //reload data from database.
        $this->assertEquals($encodedPassword, $this->object['password']);

        $this->object->password = '321';
        $newEncodedPassword = $this->object->schema()->password->encode('321');
        
        $this->object->update();

        $this->assertEquals(
            'UPDATE "Database_RowTest" SET "password" = \''.$newEncodedPassword.'\' WHERE "id" = \'6\';',
            $this->object->getLastQueryString()
        ); //only change password field.

        $this->object->getRawData();

        $this->assertNotEquals($encodedPassword, $this->object['password']);
        $this->assertEquals($newEncodedPassword, $this->object['password']);
        
        $this->object->permission = 100;
        $this->object->email = 'test@com.tw';

        $this->object->update();
        $this->assertEquals(
            'UPDATE "Database_RowTest" SET "permission" = \'100\''
            .',"email" = \'test@com.tw\' WHERE "id" = \'6\';',
            $this->object->getLastQueryString()
        ); //only change permission and email fields.

        $this->object->getRawData(); //reload data from database.
        $this->assertEquals($newEncodedPassword, $this->object['password']);

        $this->assertTrue( $this->object->delete() );
    }

    /**
     * assign() 預期的資料來源是由使用者輸入的(未經編碼)，所以會對資料欄位進行格
     * 式檢查。同時影嚮 insert() 與 update() 於儲存具有編碼動作的欄位時，
     * 會對其執行編碼動作。
     *
     * 當表格中具有非對稱編碼動作的欄位時，一般的儲存動作應該略過那些欄位。
     * 作法有二:
     * 1. query 時，不取出那些欄位。
     * 2. unchange() 那些欄位。
     */
    public function testAssignAndUpdatePassword() {
        $data = $this->mockData;
        $this->object->assign($data);
        $this->object->insert();

        $encodedPassword = $this->object->schema()->password->encode($data->password);

        //1. assign data input by user, then update
        $data = $this->mockData;
        $this->object->assign($data); 

        $this->object['permission'] = 99;
        $this->object->update();

        $this->object->getRawData(); //reload raw data from database.
        $this->assertEquals($encodedPassword, $this->object['password']);

        //2. assign data from database, then update without unchange.
        $data = $this->object->getRawData(); //load data from database.
        $this->object->assign($data); 
            //you should use factory() instead.
            //or you should set password to be 'Unchanged'.

        $this->object['permission'] = 100;
        $this->object->update();

        $this->object->getRawData(); //reload raw data from database.
        $this->assertNotEquals($encodedPassword, $this->object['password']);

        //revert data
        $data = $this->mockData;
        $this->object->assign($data);
        $this->object->update();

        //3. assign data from database, then update after unchange.
        $data = $this->object->getRawData(); //load data from database.
        $this->object->assign($data);
        $this->object->unchange('password');

        $this->object['permission'] = 100;
        $this->object->update();

        $this->object->getRawData(); //reload raw data from database.
        $this->assertEquals($encodedPassword, $this->object['password']);

        $this->assertTrue( $this->object->delete() );
    }

    /**
     * factory() 預期的資料來源是自資料庫中讀取的，不檢查資料欄位的格式。
     * update() 時也不會再次執行編碼動作。insert() 通常會失敗(因為已存在)。
     */
    public function testFactoryAndUpdatePassword() {
        $data = $this->mockData;
        $this->object->assign($data);
        $this->object->insert();

        $encodedPassword = $this->object->schema()->password->encode($data->password);

        //assign data from database.
        $data = $this->object->getRawData(); //load data from database.
        $row = $this->object->factory($data);
        
        $this->assertFalse( $row->insert() );

        $row['permission'] = 100;
        $row->update();

        $row->getRawData(); //reload raw data from database.
        $this->assertEquals($encodedPassword, $row['password']);

        $this->assertTrue( $this->object->delete() );
    }

}

// Run this test if this source file is executed directly.
if (!defined('PHPUnit_MAIN_METHOD')) {
    require_once 'PHPUnit/TextUI/TestRunner.php';

    $suite  = new PHPUnit_Framework_TestSuite('DatabaseRowWithoutSchemaTest');
    $result = PHPUnit_TextUI_TestRunner::run($suite);
}
?>
