<?php

declare(strict_types=1);

namespace Aubes\CSPBundle;

use Aubes\CSPBundle\Command\CSPCheckCommand;
use Aubes\CSPBundle\Controller\ReportController;
use Aubes\CSPBundle\DataCollector\CSPDataCollector;
use Aubes\CSPBundle\Enum\CSPDirective;
use Aubes\CSPBundle\Listener\CSPAttributeListener;
use Aubes\CSPBundle\Listener\CSPListener;
use Aubes\CSPBundle\Listener\CSPViolationLogListener;
use Aubes\CSPBundle\Model\CSPPolicy;
use Aubes\CSPBundle\Preset\CSPPreset;
use Aubes\CSPBundle\Report\ReportTo;
use Aubes\CSPBundle\Twig\CSPExtension as TwigCSPExtension;
use Aubes\CSPBundle\Uid\Generator;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class CSPBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return __DIR__;
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $presetValues = \array_map(static fn (CSPPreset $p) => $p->value, CSPPreset::cases());

        $definition->rootNode()
            ->children()
                ->arrayNode('report_logger')
                    ->children()
                        ->scalarNode('logger_id')->end()
                        ->scalarNode('level')->end()
                    ->end()
                ->end()
                ->scalarNode('default_group')->defaultNull()->end()
                ->booleanNode('auto_default')->defaultFalse()->end()
                ->booleanNode('debug')->defaultFalse()->end()
                ->arrayNode('groups')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->arrayPrototype()
                        ->children()
                            ->booleanNode('report_only')->defaultFalse()->end()

                            ->enumNode('preset')
                                ->values($presetValues)
                                ->defaultNull()
                            ->end()

                            ->arrayNode('reporting')
                                ->children()
                                    ->integerNode('max_age')->end()
                                    ->scalarNode('group_name')
                                        ->defaultNull()
                                        ->validate()
                                            ->ifTrue(static fn (?string $v) => $v !== null && !\preg_match('/^[a-zA-Z0-9_-]+$/', $v))
                                            ->thenInvalid('Reporting group_name "%s" must contain only alphanumeric characters, hyphens and underscores')
                                        ->end()
                                    ->end()
                                    ->arrayNode('endpoints')
                                        ->scalarPrototype()->end()
                                    ->end()
                                    ->booleanNode('backward_compatibility')->defaultFalse()->end()
                                ->end()
                            ->end()

                            ->arrayNode('policies')
                                ->arrayPrototype()
                                    ->scalarPrototype()->end()
                                ->end()
                                ->validate()
                                    ->ifTrue(static function (array $policies): bool {
                                        $allowed = \array_map(
                                            static fn (CSPDirective $d) => \str_replace('-', '_', $d->value),
                                            CSPDirective::cases(),
                                        );

                                        return \array_diff(\array_keys($policies), $allowed) !== [];
                                    })
                                    ->then(static function (array $policies): never {
                                        $allowed = \array_map(
                                            static fn (CSPDirective $d) => \str_replace('-', '_', $d->value),
                                            CSPDirective::cases(),
                                        );
                                        $unknown = \array_diff(\array_keys($policies), $allowed);

                                        throw new \InvalidArgumentException(\sprintf('Unknown CSP directive(s): "%s"', \implode('", "', $unknown)));
                                    })
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{default_group: ?string, auto_default: bool, debug: bool, groups: array<string, array{report_only: bool, preset: ?string, reporting?: array{group_name: ?string, max_age: int, endpoints: list<string>, backward_compatibility: bool}, policies: array<string, list<string>>}>, report_logger?: array{logger_id?: string, level?: string}} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()
            ->set(CSP::class)

            ->set('.aubes_csp.uid_generator', Generator::class)

            ->set(ReportController::class)
                ->autowire()
                ->autoconfigure()

            ->set(CSPAttributeListener::class)
                ->args([new Reference(CSP::class)])
                ->autoconfigure()

            ->set(CSPListener::class)
                ->args([
                    '$csp' => new Reference(CSP::class),
                    '$dispatcher' => new Reference('event_dispatcher'),
                ])
                ->autoconfigure()

            ->set(CSPCheckCommand::class)
                ->args([new Reference(CSP::class)])
                ->autoconfigure()
        ;

        if (\class_exists(\Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class)) {
            $dataCollector = new Definition(CSPDataCollector::class, [
                new Reference(CSP::class),
            ]);
            $dataCollector->addTag('data_collector', [
                'template' => '@CSP/data_collector/csp.html.twig',
                'id' => 'csp',
            ]);
            $builder->setDefinition(CSPDataCollector::class, $dataCollector);
        }

        if (\class_exists(\Twig\Extension\AbstractExtension::class)) {
            $container->services()
                ->set(TwigCSPExtension::class)
                    ->args([
                        new Reference(CSP::class),
                        new Reference('.aubes_csp.uid_generator'),
                        new Reference('request_stack'),
                    ])
                    ->tag('twig.extension')
            ;
        }

        $csp = $builder->getDefinition(CSP::class);

        if ($config['default_group'] === null) {
            if (\count($config['groups']) > 1) {
                throw new \InvalidArgumentException('You must set default group when multiple groups are defined');
            }
            $defaultGroup = \array_key_first($config['groups']);
        } else {
            $defaultGroup = $config['default_group'];
        }

        if ($config['debug']) {
            foreach ($config['groups'] as &$groupConfig) {
                $groupConfig['report_only'] = true;
            }
            unset($groupConfig);
        }

        $cspPolicies = $this->buildCspPolicies($config['groups'], $builder);

        $csp->setArgument('$defaultGroup', $defaultGroup);
        $csp->setArgument('$autoDefault', $config['auto_default']);
        $csp->setArgument('$groups', $cspPolicies);

        if (isset($config['report_logger'])) {
            $this->configureReportLogger($config['report_logger'], $container, $builder);
        }
    }

    /**
     * @param array<string, array{report_only: bool, preset: ?string, reporting?: array{group_name: ?string, max_age: int, endpoints: list<string>, backward_compatibility: bool}, policies: array<string, list<string>>}> $groups
     *
     * @return array<string, Definition>
     */
    private function buildCspPolicies(array $groups, ContainerBuilder $builder): array
    {
        $cspPolicies = [];
        /** @var list<string> $reportRoutes */
        $reportRoutes = [];

        foreach ($groups as $groupName => $cspConfig) {
            $reportTo = null;

            if (isset($cspConfig['reporting'])) {
                $reporting = $cspConfig['reporting'];
                $reportTo = new Definition(ReportTo::class, [
                    new Reference('router'),
                    $reporting['group_name'] ?? $groupName,
                    $reporting['max_age'],
                    $reporting['endpoints'],
                ]);

                \array_push($reportRoutes, ...$reporting['endpoints']);
            }

            $presetPolicies = $this->resolvePreset($cspConfig['preset']);
            $userPolicies = $this->resolveUserPolicies($cspConfig['policies']);

            $policies = $this->mergePolicies($presetPolicies, $userPolicies);

            $cspPolicies[$groupName] = new Definition(CSPPolicy::class, [
                $reportTo,
                $policies,
                $cspConfig['report_only'],
                $cspConfig['reporting']['backward_compatibility'] ?? false,
            ]);
        }

        $cspListener = $builder->getDefinition(CSPListener::class);
        $cspListener->setArgument('$reportRoutes', $reportRoutes);

        return $cspPolicies;
    }

    /**
     * @return array<string, list<string>>
     */
    private function resolvePreset(?string $preset): array
    {
        if ($preset === null) {
            return [];
        }

        return CSPPreset::from($preset)->policies();
    }

    /**
     * @param array<string, list<string>> $rawPolicies
     *
     * @return array<string, list<string>>
     */
    private function resolveUserPolicies(array $rawPolicies): array
    {
        $policies = [];

        foreach (CSPDirective::cases() as $directive) {
            $directiveConfig = \str_replace('-', '_', $directive->value);

            if (isset($rawPolicies[$directiveConfig])) {
                $policies[$directive->value] = $rawPolicies[$directiveConfig];
            }
        }

        return $policies;
    }

    /**
     * @param array<string, list<string>> $preset
     * @param array<string, list<string>> $user
     *
     * @return array<string, list<string>>
     */
    private function mergePolicies(array $preset, array $user): array
    {
        $merged = $preset;

        foreach ($user as $directive => $sources) {
            if (isset($merged[$directive])) {
                $merged[$directive] = \array_values(\array_unique([...$merged[$directive], ...$sources]));
            } else {
                $merged[$directive] = $sources;
            }
        }

        return $merged;
    }

    /**
     * @param array{logger_id?: string, level?: string} $reportLoggerConfig
     */
    private function configureReportLogger(array $reportLoggerConfig, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()
            ->set(CSPViolationLogListener::class)
                ->autoconfigure()
        ;

        $listener = $builder->getDefinition(CSPViolationLogListener::class);

        if (isset($reportLoggerConfig['logger_id'])) {
            $listener->setArgument('$logger', new Reference($reportLoggerConfig['logger_id']));
        } else {
            $listener->setArgument('$logger', new Reference('logger'));
        }

        if (isset($reportLoggerConfig['level'])) {
            $listener->setArgument('$level', $reportLoggerConfig['level']);
        }
    }
}
