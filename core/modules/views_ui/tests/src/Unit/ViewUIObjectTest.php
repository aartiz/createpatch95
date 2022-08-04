<?php

namespace Drupal\Tests\views_ui\Unit;

use Drupal\Core\TempStore\Lock;
use Drupal\Tests\UnitTestCase;
use Drupal\views\Entity\View;
use Drupal\views_ui\ViewUI;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @coversDefaultClass \Drupal\views_ui\ViewUI
 * @group views_ui
 */
class ViewUIObjectTest extends UnitTestCase {

  /**
   * Tests entity method decoration.
   *
   * @dataProvider providerEntityDecoration
   */
  public function testEntityDecoration($method, $arguments) {
    $storage = $this->getMockBuilder('Drupal\views\Entity\View')
      ->onlyMethods([$method])
      ->setConstructorArgs([[], 'view'])
      ->getMock();
    $executable = $this->getMockBuilder('Drupal\views\ViewExecutable')
      ->disableOriginalConstructor()
      ->setConstructorArgs([$storage])
      ->getMock();
    $storage->set('executable', $executable);

    $view_ui = new ViewUI($storage);

    $method_mock = $storage->expects($this->once())->method($method);
    foreach ($arguments as $argument) {
      $method_mock->with($this->equalTo($argument));
    }
    call_user_func_array([$view_ui, $method], $arguments);
  }

  /**
   * Data provider for ::testEntityDecoration.
   */
  public function providerEntityDecoration() {
    $methods = [
      ['setOriginalId', [12]],
      ['setStatus', [TRUE]],
      ['enforceIsNew', [FALSE]],
      ['setSyncing', [TRUE]],
      ['setUninstalling', [TRUE]],
    ];

    $reflection = new \ReflectionClass('Drupal\Core\Config\Entity\ConfigEntityInterface');
    $interface_methods = [];
    foreach ($reflection->getMethods() as $reflection_method) {
      $interface_methods[] = $reflection_method->getName();
      if (count($reflection_method->getParameters()) == 0) {
        $methods[] = [$reflection_method->getName(), []];
      }
    }
    return $methods;
  }

  /**
   * Tests the isLocked method.
   */
  public function testIsLocked() {
    $storage = $this->getMockBuilder('Drupal\views\Entity\View')
      ->setConstructorArgs([[], 'view'])
      ->getMock();
    $executable = $this->getMockBuilder('Drupal\views\ViewExecutable')
      ->disableOriginalConstructor()
      ->setConstructorArgs([$storage])
      ->getMock();
    $storage->set('executable', $executable);
    $account = $this->createMock('Drupal\Core\Session\AccountInterface');
    $account->expects($this->exactly(2))
      ->method('id')
      ->will($this->returnValue(1));

    $container = new ContainerBuilder();
    $container->set('current_user', $account);
    \Drupal::setContainer($container);

    $view_ui = new ViewUI($storage);

    // A view_ui without a lock object is not locked.
    $this->assertFalse($view_ui->isLocked());

    // Set the lock object with a different owner than the mocked account above.
    $lock = new Lock(2, (int) $_SERVER['REQUEST_TIME']);
    $view_ui->setLock($lock);
    $this->assertTrue($view_ui->isLocked());

    // Set a different lock object with the same object as the mocked account.
    $lock = new Lock(1, (int) $_SERVER['REQUEST_TIME']);
    $view_ui->setLock($lock);
    $this->assertFalse($view_ui->isLocked());

    $view_ui->unsetLock(NULL);
    $this->assertFalse($view_ui->isLocked());
  }

  /**
   * Tests serialization of the ViewUI object.
   */
  public function testSerialization() {
    // Set a container so the DependencySerializationTrait has it.
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);

    $storage = new View([], 'view');
    $executable = $this->getMockBuilder('Drupal\views\ViewExecutable')
      ->disableOriginalConstructor()
      ->setConstructorArgs([$storage])
      ->getMock();
    $storage->set('executable', $executable);

    $view_ui = new ViewUI($storage);

    // Make sure the executable is returned before serializing.
    $this->assertInstanceOf('Drupal\views\ViewExecutable', $view_ui->getExecutable());

    $serialized = serialize($view_ui);

    // Make sure the ViewExecutable class is not found in the serialized string.
    $this->assertStringNotContainsString('"Drupal\views\ViewExecutable"', $serialized);

    $unserialized = unserialize($serialized);
    $this->assertInstanceOf('Drupal\views_ui\ViewUI', $unserialized);
  }

}
