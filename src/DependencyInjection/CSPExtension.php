<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\DependencyInjection;

use Aubes\CSPBundle\CSP;
use Aubes\CSPBundle\CSPDirective;
use Aubes\CSPBundle\CSPPolicy;
use Aubes\CSPBundle\CSPSource;
use Aubes\CSPBundle\Listener\CSPListener;
use Aubes\CSPBundle\Report\ReportTo;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * @SuppressWarnings(PMD.CouplingBetweenObjects)
 * @SuppressWarnings(PMD.ElseExpression)
 */
class CSPExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        $csp = $container->getDefinition(CSP::class);

        if ($config['default_group'] === null) {
            if (\count($config['groups']) > 1) {
                throw new \InvalidArgumentException('You must set default group when multiple groups are defined');
            } else {
                $defaultGroup = \array_key_first($config['groups']);
            }
        } else {
            $defaultGroup = $config['default_group'];
        }

        $cspPolicies = $this->buildCspPolicies($config, $container);

        $csp->setArgument('$defaultGroup', $defaultGroup);
        $csp->setArgument('$autoDefault', $config['auto_default']);
        $csp->setArgument('$groups', $cspPolicies);

        $this->buildReportController($config, $csp);
    }

    protected function buildCspPolicies(array $config, ContainerBuilder $container): array
    {
        $cspPolicies = [];
        $reportRoutes = [];

        foreach ($config['groups'] as $groupName => $cspConfig) {
            $reportTo = null;

            if (isset($cspConfig['reporting'])) {
                $reportTo = new Definition(ReportTo::class, [
                    new Reference('router'),
                    $cspConfig['reporting']['group_name'] ?? $groupName,
                    $cspConfig['reporting']['max_age'],
                    $cspConfig['reporting']['endpoints'],
                ]);

                $reportRoutes += $cspConfig['reporting']['endpoints'];
            }

            $policies = [];
            foreach (CSPDirective::ALL as $directive) {
                if (!empty($cspConfig['policies'][$directive])) {
                    $policies[$directive] = $this->getPolicy($cspConfig['policies'][$directive]);
                }
            }

            $cspPolicies[$groupName] = new Definition(CSPPolicy::class, [
                $reportTo,
                $policies,
                $cspConfig['report_only'],
                $cspConfig['reporting']['backward_compatibility'] ?? false,
            ]);
        }

        $cspListener = $container->getDefinition(CSPListener::class);
        $cspListener->setArgument('$reportRoutes', $reportRoutes);

        return $cspPolicies;
    }

    protected function buildReportController(array $config, Definition $csp): void
    {
        if (!isset($config['report_logger'])) {
            return;
        }

        $configReportLogger = $config['report_logger'];

        if (isset($configReportLogger['logger_id'])) {
            $csp->setArgument('$logger', new Reference($configReportLogger['logger_id']));
        }

        if (isset($configReportLogger['level'])) {
            $csp->setArgument('$level', new Reference($configReportLogger['level']));
        }
    }

    protected function getPolicy(array $policy): array
    {
        foreach ($policy as $key => $source) {
            if (\in_array($source, CSPSource::ALL)) {
                $policy[$key] = "'$source'";
            }
        }

        return $policy;
    }
}
