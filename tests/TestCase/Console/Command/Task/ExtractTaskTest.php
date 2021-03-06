<?php
/**
 * ExtractTaskTest file
 *
 * Test Case for i18n extraction shell task
 *
 * CakePHP :  Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP Project
 * @since         1.2.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Test\TestCase\Console\Command\Task;

use Cake\Console\Command\Task\ExtractTask;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\TestSuite\TestCase;
use Cake\Utility\Folder;

/**
 * ExtractTaskTest class
 *
 */
class ExtractTaskTest extends TestCase {

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		$out = $this->getMock('Cake\Console\ConsoleOutput', array(), array(), '', false);
		$in = $this->getMock('Cake\Console\ConsoleInput', array(), array(), '', false);

		$this->Task = $this->getMock(
			'Cake\Console\Command\Task\ExtractTask',
			array('in', 'out', 'err', '_stop'),
			array($out, $out, $in)
		);
		$this->path = TMP . 'tests/extract_task_test';
		new Folder($this->path . DS . 'locale', true);
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		parent::tearDown();
		unset($this->Task);

		$Folder = new Folder($this->path);
		$Folder->delete();
		Plugin::unload();
	}

/**
 * testExecute method
 *
 * @return void
 */
	public function testExecute() {
		$this->Task->interactive = false;

		$this->Task->params['paths'] = TEST_APP . 'TestApp/Template/Pages';
		$this->Task->params['output'] = $this->path . DS;
		$this->Task->params['extract-core'] = 'no';
		$this->Task->expects($this->never())->method('err');
		$this->Task->expects($this->any())->method('in')
			->will($this->returnValue('y'));
		$this->Task->expects($this->never())->method('_stop');

		$this->Task->execute();
		$this->assertTrue(file_exists($this->path . DS . 'default.pot'));
		$result = file_get_contents($this->path . DS . 'default.pot');

		$this->assertFalse(file_exists($this->path . DS . 'cake.pot'));

		// extract.ctp
		$pattern = '/\#: (\\\\|\/)extract\.ctp:\d+;\d+\n';
		$pattern .= 'msgid "You have %d new message."\nmsgid_plural "You have %d new messages."/';
		$this->assertRegExp($pattern, $result);

		$pattern = '/msgid "You have %d new message."\nmsgstr ""/';
		$this->assertNotRegExp($pattern, $result, 'No duplicate msgid');

		$pattern = '/\#: (\\\\|\/)extract\.ctp:\d+\n';
		$pattern .= 'msgid "You deleted %d message."\nmsgid_plural "You deleted %d messages."/';
		$this->assertRegExp($pattern, $result);

		$pattern = '/\#: (\\\\|\/)extract\.ctp:\d+\nmsgid "';
		$pattern .= 'Hot features!';
		$pattern .= '\\\n - No Configuration: Set-up the database and let the magic begin';
		$pattern .= '\\\n - Extremely Simple: Just look at the name...It\'s Cake';
		$pattern .= '\\\n - Active, Friendly Community: Join us #cakephp on IRC. We\'d love to help you get started';
		$pattern .= '"\nmsgstr ""/';
		$this->assertRegExp($pattern, $result);

		$this->assertContains('msgid "double \\"quoted\\""', $result, 'Strings with quotes not handled correctly');
		$this->assertContains("msgid \"single 'quoted'\"", $result, 'Strings with quotes not handled correctly');

		// extract.ctp - reading the domain.pot
		$result = file_get_contents($this->path . DS . 'domain.pot');

		$pattern = '/msgid "You have %d new message."\nmsgid_plural "You have %d new messages."/';
		$this->assertNotRegExp($pattern, $result);
		$pattern = '/msgid "You deleted %d message."\nmsgid_plural "You deleted %d messages."/';
		$this->assertNotRegExp($pattern, $result);

		$pattern = '/msgid "You have %d new message \(domain\)."\nmsgid_plural "You have %d new messages \(domain\)."/';
		$this->assertRegExp($pattern, $result);
		$pattern = '/msgid "You deleted %d message \(domain\)."\nmsgid_plural "You deleted %d messages \(domain\)."/';
		$this->assertRegExp($pattern, $result);
	}

/**
 * testExtractCategory method
 *
 * @return void
 */
	public function testExtractCategory() {
		$this->Task->interactive = false;

		$this->Task->params['paths'] = TEST_APP . 'TestApp' . DS . 'Template' . DS . 'Pages';
		$this->Task->params['output'] = $this->path . DS;
		$this->Task->params['extract-core'] = 'no';
		$this->Task->params['merge'] = 'no';
		$this->Task->expects($this->never())->method('err');
		$this->Task->expects($this->any())->method('in')
			->will($this->returnValue('y'));
		$this->Task->expects($this->never())->method('_stop');

		$this->Task->execute();
		$this->assertTrue(file_exists($this->path . DS . 'LC_TIME' . DS . 'default.pot'));

		$result = file_get_contents($this->path . DS . 'default.pot');

		$this->assertNotContains('You have a new message (category: LC_TIME).', $result);
	}

/**
 * test exclusions
 *
 * @return void
 */
	public function testExtractWithExclude() {
		$this->Task->interactive = false;

		$this->Task->params['paths'] = TEST_APP . 'TestApp/Template';
		$this->Task->params['output'] = $this->path . DS;
		$this->Task->params['exclude'] = 'Pages,Layout';
		$this->Task->params['extract-core'] = 'no';

		$this->Task->expects($this->any())->method('in')
			->will($this->returnValue('y'));

		$this->Task->execute();
		$this->assertTrue(file_exists($this->path . DS . 'default.pot'));
		$result = file_get_contents($this->path . DS . 'default.pot');

		$pattern = '/\#: .*extract\.ctp:\d+\n/';
		$this->assertNotRegExp($pattern, $result);

		$pattern = '/\#: .*default\.ctp:\d+\n/';
		$this->assertNotRegExp($pattern, $result);
	}

/**
 * test extract can read more than one path.
 *
 * @return void
 */
	public function testExtractMultiplePaths() {
		$this->Task->interactive = false;

		$this->Task->params['paths'] =
			TEST_APP . 'TestApp/Template/Pages,' .
			TEST_APP . 'TestApp/Template/Posts';

		$this->Task->params['output'] = $this->path . DS;
		$this->Task->params['extract-core'] = 'no';
		$this->Task->expects($this->never())->method('err');
		$this->Task->expects($this->never())->method('_stop');
		$this->Task->execute();

		$result = file_get_contents($this->path . DS . 'default.pot');

		$pattern = '/msgid "Add User"/';
		$this->assertRegExp($pattern, $result);
	}

/**
 * Tests that it is possible to exclude plugin paths by enabling the param option for the ExtractTask
 *
 * @return void
 */
	public function testExtractExcludePlugins() {
		Configure::write('App.namespace', 'TestApp');
		$this->out = $this->getMock('Cake\Console\ConsoleOutput', array(), array(), '', false);
		$this->in = $this->getMock('Cake\Console\ConsoleInput', array(), array(), '', false);
		$this->Task = $this->getMock('Cake\Console\Command\Task\ExtractTask',
			array('_isExtractingApp', '_extractValidationMessages', 'in', 'out', 'err', 'clear', '_stop'),
			array($this->out, $this->out, $this->in)
		);
		$this->Task->expects($this->exactly(2))
			->method('_isExtractingApp')
			->will($this->returnValue(true));

		$this->Task->params['paths'] = TEST_APP . 'TestApp/';
		$this->Task->params['output'] = $this->path . DS;
		$this->Task->params['exclude-plugins'] = true;

		$this->Task->execute();
		$result = file_get_contents($this->path . DS . 'default.pot');
		$this->assertNotRegExp('#TestPlugin#', $result);
	}

/**
 * Test that is possible to extract messages form a single plugin
 *
 * @return void
 */
	public function testExtractPlugin() {
		Configure::write('App.namespace', 'TestApp');

		$this->out = $this->getMock('Cake\Console\ConsoleOutput', array(), array(), '', false);
		$this->in = $this->getMock('Cake\Console\ConsoleInput', array(), array(), '', false);
		$this->Task = $this->getMock('Cake\Console\Command\Task\ExtractTask',
			array('_isExtractingApp', 'in', 'out', 'err', 'clear', '_stop'),
			array($this->out, $this->out, $this->in)
		);

		$this->Task->params['output'] = $this->path . DS;
		$this->Task->params['plugin'] = 'TestPlugin';

		$this->markTestIncomplete('Extracting validation messages from plugin models is not working.');
		$this->Task->execute();
		$result = file_get_contents($this->path . DS . 'default.pot');
		$this->assertNotRegExp('#Pages#', $result);
		$this->assertRegExp('/translate\.ctp:\d+/', $result);
		$this->assertContains('This is a translatable string', $result);
		$this->assertContains('I can haz plugin model validation message', $result);
	}

/**
 * Tests that the task will inspect application models and extract the validation messages from them
 *
 * @return void
 */
	public function testExtractModelValidation() {
		$this->markTestIncomplete('Extracting validation messages is not working right now.');
		Configure::write('App.namespace', 'TestApp');
		Plugin::load('TestPlugin');

		$this->out = $this->getMock('Cake\Console\ConsoleOutput', array(), array(), '', false);
		$this->in = $this->getMock('Cake\Console\ConsoleInput', array(), array(), '', false);
		$this->Task = $this->getMock('Cake\Console\Command\Task\ExtractTask',
			array('_isExtractingApp', 'in', 'out', 'err', 'clear', '_stop'),
			array($this->out, $this->out, $this->in)
		);
		$this->Task->expects($this->exactly(2))
			->method('_isExtractingApp')
			->will($this->returnValue(true));

		$this->Task->params['paths'] = TEST_APP . 'TestApp/';
		$this->Task->params['output'] = $this->path . DS;
		$this->Task->params['extract-core'] = 'no';
		$this->Task->params['exclude-plugins'] = true;
		$this->Task->params['ignore-model-validation'] = false;

		$this->Task->execute();
		$result = file_get_contents($this->path . DS . 'default.pot');

		$pattern = preg_quote('#Model/PersisterOne.php:validation for field title#', '\\');
		$this->assertRegExp($pattern, $result);

		$pattern = preg_quote('#Model/PersisterOne.php:validation for field body#', '\\');
		$this->assertRegExp($pattern, $result);

		$pattern = '#msgid "Post title is required"#';
		$this->assertRegExp($pattern, $result);

		$pattern = '#msgid "You may enter up to %s chars \(minimum is %s chars\)"#';
		$this->assertRegExp($pattern, $result);

		$pattern = '#msgid "Post body is required"#';
		$this->assertRegExp($pattern, $result);

		$pattern = '#msgid "Post body is super required"#';
		$this->assertRegExp($pattern, $result);

		$this->assertContains('msgid "double \\"quoted\\" validation"', $result, 'Strings with quotes not handled correctly');
		$this->assertContains("msgid \"single 'quoted' validation\"", $result, 'Strings with quotes not handled correctly');
	}

/**
 *  Test that the extract shell can obtain validation messages from models inside a specific plugin
 *
 * @return void
 */
	public function testExtractModelValidationInPlugin() {
		$this->markTestIncomplete('Extracting validation messages is not working right now.');
		Configure::write('App.namespace', 'TestApp');
		Plugin::load('TestPlugin');
		$this->out = $this->getMock('Cake\Console\ConsoleOutput', array(), array(), '', false);
		$this->in = $this->getMock('Cake\Console\ConsoleInput', array(), array(), '', false);
		$this->Task = $this->getMock('Cake\Console\Command\Task\ExtractTask',
			array('_isExtractingApp', 'in', 'out', 'err', 'clear', '_stop'),
			array($this->out, $this->out, $this->in)
		);

		$this->Task->params['output'] = $this->path . DS;
		$this->Task->params['ignore-model-validation'] = false;
		$this->Task->params['plugin'] = 'TestPlugin';

		$this->Task->execute();
		$result = file_get_contents($this->path . DS . 'test_plugin.pot');

		$pattern = preg_quote('#Model/TestPluginPost.php:validation for field title#', '\\');
		$this->assertRegExp($pattern, $result);

		$pattern = preg_quote('#Model/TestPluginPost.php:validation for field body#', '\\');
		$this->assertRegExp($pattern, $result);

		$pattern = '#msgid "Post title is required"#';
		$this->assertRegExp($pattern, $result);

		$pattern = '#msgid "Post body is required"#';
		$this->assertRegExp($pattern, $result);

		$pattern = '#msgid "Post body is super required"#';
		$this->assertRegExp($pattern, $result);

		$pattern = '#Plugin/TestPlugin/Model/TestPluginPost.php:validation for field title#';
		$this->assertNotRegExp($pattern, $result);
	}

/**
 *  Test that the extract shell overwrites existing files with the overwrite parameter
 *
 * @return void
 */
	public function testExtractOverwrite() {
		Configure::write('App.namespace', 'TestApp');
		$this->Task->interactive = false;

		$this->Task->params['paths'] = TEST_APP . 'TestApp/';
		$this->Task->params['output'] = $this->path . DS;
		$this->Task->params['extract-core'] = 'no';
		$this->Task->params['overwrite'] = true;

		file_put_contents($this->path . DS . 'default.pot', 'will be overwritten');
		$this->assertTrue(file_exists($this->path . DS . 'default.pot'));
		$original = file_get_contents($this->path . DS . 'default.pot');

		$this->Task->execute();
		$result = file_get_contents($this->path . DS . 'default.pot');
		$this->assertNotEquals($original, $result);
	}

/**
 *  Test that the extract shell scans the core libs
 *
 * @return void
 */
	public function testExtractCore() {
		Configure::write('App.namespace', 'TestApp');
		$this->Task->interactive = false;

		$this->Task->params['paths'] = TEST_APP . 'TestApp/';
		$this->Task->params['output'] = $this->path . DS;
		$this->Task->params['extract-core'] = 'yes';

		$this->Task->execute();
		$this->assertTrue(file_exists($this->path . DS . 'cake.pot'));
		$result = file_get_contents($this->path . DS . 'cake.pot');

		$pattern = '/#: Console\/Templates\//';
		$this->assertNotRegExp($pattern, $result);

		$pattern = '/#: Test\//';
		$this->assertNotRegExp($pattern, $result);
	}
}
