<?php

namespace Instante\Tracy\Login\DI;

use Instante\Tracy\Login\DebugLogin;
use Nette\DI\CompilerExtension;


class DebugLoginExtension extends CompilerExtension
{
    public array $defaults = [
        'dao'        => [
            'class'  => 'Instante\Tracy\Login\DoctrineUserDao',
            'entity' => 'App\Model\User\User',
        ],
        'enabled'    => false,
        'identifier' => 'email',
    ];

    public function loadConfiguration()
    {
        $builder = $this->getContainerBuilder();
        $debugEnvironment = self::isDebugEnvironment($builder->parameters);
        $this->config['debugEnvironment'] = $debugEnvironment;

        $config = $this->getConfig() + $this->defaults;
        $this->setConfig($config);

        if ($this->config['enabled'] && $debugEnvironment) {
            $builder->addDefinition($this->prefix('debugLogin'))
                ->setFactory(DebugLogin::class)
                ->addSetup('setConfig', [$config]);

            $entity = null;
            if (is_array($config['dao'])) {
                $class = $config['dao']['class'];
                if (key_exists('entity', $config['dao'])) {
                    $entity = $config['dao']['entity'];
                }
            } else {
                preg_match('~^(.*?)(?:\((.*)\))?$~', $config['dao'], $matches);
                $class = $matches[1];
                if (key_exists(2, $matches)) {
                    $entity = $matches[2];
                }
            }

            $builder->addDefinition($this->prefix('userDao'))
                ->setFactory($class);

            if ($entity !== null) {
                $builder->getDefinition($this->prefix('userDao'))->setArguments(['entity' => $entity]);
            }
        }
    }

    public function beforeCompile()
    {
        $builder = $this->getContainerBuilder();

        if ($this->config['enabled'] && self::isDebugEnvironment($builder->parameters) && $application = $builder->getByType('Nette\Application\Application')) {
            $builder->getDefinition($application)->addSetup('@Tracy\Bar::addPanel', [
                $builder->getDefinition($this->prefix('debugLogin'))
            ]);

            $builder->getDefinition($builder->getByType('Nette\Application\IRouter') ?: 'router')
                ->addSetup('Instante\Tracy\Login\DebugLogin::addRoutes');
        }

    }

    public static function isDebugEnvironment($parameters)
    {
        if (key_exists('environment', $parameters)) {
            if ($parameters['environment'] === 'development') {
                return TRUE;
            }
            return FALSE;
        }

        return $parameters['debugMode'];
    }
}
