<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Magento
 * @package     performance_tests
 * @subpackage  unit_tests
 * @copyright   Copyright (c) 2012 Magento Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Magento_Performance_TestsuiteTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Magento_Performance_Testsuite
     */
    protected $_object;

    /**
     * @var Magento_Performance_Config
     */
    protected $_config;

    /**
     * @var Magento_Application|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_application;

    /**
     * @var Magento_Performance_Scenario_HandlerInterface|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_handler;

    /**
     * @var string
     */
    protected $_fixtureDir;

    protected function setUp()
    {
        $this->_fixtureDir = __DIR__ . DIRECTORY_SEPARATOR . '_files';
        $fixtureConfigData = include($this->_fixtureDir . DIRECTORY_SEPARATOR . 'config_data.php');

        $shell = $this->getMock('Magento_Shell', array('execute'));
        $this->_config = new Magento_Performance_Config(
            $fixtureConfigData,
            $this->_fixtureDir,
            $this->_fixtureDir . '/app_base_dir'
        );
        $this->_application = $this->getMock(
            'Magento_Application', array('applyFixtures'), array($this->_config, $shell)
        );
        $this->_handler = $this->getMockForAbstractClass('Magento_Performance_Scenario_HandlerInterface');
        $this->_object = new Magento_Performance_Testsuite($this->_config, $this->_application, $this->_handler);
    }

    protected function tearDown()
    {
        $this->_config = null;
        $this->_application = null;
        $this->_handler = null;
        $this->_object = null;
    }

    /**
     * Setup expectation of a scenario warm up invocation
     *
     * @param string $scenarioTitle
     * @param string $scenarioFile
     * @param integer $invocationIndex
     * @param PHPUnit_Framework_MockObject_Stub $returnStub
     */
    protected function _expectScenarioWarmUp(
        $scenarioTitle, $scenarioFile, $invocationIndex, PHPUnit_Framework_MockObject_Stub $returnStub = null
    ) {
        $scenarioFilePath = $this->_fixtureDir . DIRECTORY_SEPARATOR . $scenarioFile;

        /** @var $invocationMocker PHPUnit_Framework_MockObject_Builder_InvocationMocker */
        $invocationMocker = $this->_handler->expects($this->at($invocationIndex));
        $invocationMocker
            ->method('run')
            ->with(
                $this->logicalAnd(
                    $this->isInstanceOf('Magento_Performance_Scenario'),
                    $this->objectHasAttribute('_title', $scenarioTitle),
                    $this->objectHasAttribute('_file', $scenarioFilePath)
                ),
                $this->isNull()
            )
        ;
        if ($returnStub) {
            $invocationMocker->will($returnStub);
        }
    }

    /**
     * Setup expectation of a scenario invocation with report generation
     *
     * @param string $scenarioTitle
     * @param string $scenarioFile
     * @param integer $invocationIndex
     * @param PHPUnit_Framework_MockObject_Stub $returnStub
     */
    protected function _expectScenarioRun(
        $scenarioTitle, $scenarioFile, $invocationIndex, PHPUnit_Framework_MockObject_Stub $returnStub = null
    ) {
        $scenarioFilePath = $this->_fixtureDir . DIRECTORY_SEPARATOR . $scenarioFile;
        $reportFile = basename($scenarioFile, '.jmx') . '.jtl';

        /** @var $invocationMocker PHPUnit_Framework_MockObject_Builder_InvocationMocker */
        $invocationMocker = $this->_handler->expects($this->at($invocationIndex));
        $invocationMocker
            ->method('run')
            ->with(
                $this->logicalAnd(
                    $this->isInstanceOf('Magento_Performance_Scenario'),
                    $this->objectHasAttribute('_title', $scenarioTitle),
                    $this->objectHasAttribute('_file', $scenarioFilePath)
                ),
                $this->_fixtureDir . DIRECTORY_SEPARATOR . 'report' . DIRECTORY_SEPARATOR . $reportFile
            )
        ;
        if ($returnStub) {
            $invocationMocker->will($returnStub);
        }
    }

    public function testRun()
    {
        $this->_expectScenarioWarmUp('Scenario with Error', 'scenario_error.jmx', 0);
        $this->_expectScenarioRun('Scenario with Error', 'scenario_error.jmx', 1);

        /* Warm up is disabled for scenario */
        $this->_expectScenarioRun('Scenario with Failure', 'scenario_failure.jmx', 2);

        $this->_expectScenarioWarmUp('Scenario', 'scenario.jmx', 3);
        $this->_expectScenarioRun('Scenario', 'scenario.jmx', 4);

        $this->_object->run();
    }

    public function testOnScenarioRun()
    {
        $this->_handler
            ->expects($this->any())
            ->method('run')
        ;
        $notifications = array();
        $this->_object->onScenarioRun(function ($scenario) use (&$notifications) {
            $notifications[] = $scenario->getFile();
        });
        $this->_object->run();
        $this->assertEquals(array(
            $this->_fixtureDir . DIRECTORY_SEPARATOR . 'scenario_error.jmx',
            $this->_fixtureDir . DIRECTORY_SEPARATOR . 'scenario_failure.jmx',
            $this->_fixtureDir . DIRECTORY_SEPARATOR . 'scenario.jmx'
        ), $notifications);
    }

    /**
     * @expectedException BadFunctionCallException
     */
    public function testOnScenarioRunException()
    {
        $this->_object->onScenarioRun('invalid_callback');
    }

    public function testOnScenarioFailure()
    {
        $scenario = new Magento_Performance_Scenario('Scenario with Error', 'scenario_error.jmx', array(), array(),
            array());
        $scenarioOneFailure = $this->throwException(
            new Magento_Performance_Scenario_FailureException($scenario)
        );
        $this->_expectScenarioWarmUp('Scenario with Error', 'scenario_error.jmx', 0, $scenarioOneFailure);
        $this->_expectScenarioRun('Scenario with Error', 'scenario_error.jmx', 1, $scenarioOneFailure);

        /* Warm up is disabled for scenario */
        $scenario = new Magento_Performance_Scenario('Scenario with Failure', 'scenario_failure.jmx', array(), array(),
            array());
        $scenarioTwoFailure = $this->throwException(
            new Magento_Performance_Scenario_FailureException($scenario)
        );
        $this->_expectScenarioRun('Scenario with Failure', 'scenario_failure.jmx', 2, $scenarioTwoFailure);

        $scenario = new Magento_Performance_Scenario('Scenario', 'scenario.jmx', array(), array(), array());
        $scenarioThreeFailure = $this->throwException(
            new Magento_Performance_Scenario_FailureException($scenario)
        );
        $this->_expectScenarioWarmUp('Scenario', 'scenario.jmx', 3);
        $this->_expectScenarioRun('Scenario', 'scenario.jmx', 4, $scenarioThreeFailure);

        $notifications = array();
        $this->_object->onScenarioFailure(
            function (Magento_Performance_Scenario_FailureException $actualFailure) use (&$notifications) {
                $notifications[] = $actualFailure->getScenario()->getFile();
            }
        );
        $this->_object->run();
        $this->assertEquals(array('scenario_error.jmx', 'scenario_failure.jmx', 'scenario.jmx'), $notifications);
    }

    /**
     * @expectedException BadFunctionCallException
     */
    public function testOnScenarioFailureException()
    {
        $this->_object->onScenarioFailure(array($this, 'invalid_callback'));
    }
}
