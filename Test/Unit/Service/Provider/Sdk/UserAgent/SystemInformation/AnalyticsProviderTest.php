<?php

/**
 * Copyright © Klevu Oy. All rights reserved. See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Klevu\Analytics\Test\Unit\Service\Provider\Sdk\UserAgent\SystemInformation;

// phpcs:ignore SlevomatCodingStandard.Namespaces.UseOnlyWhitelistedNamespaces.NonFullyQualified
use Composer\InstalledVersions;
use Klevu\Analytics\Service\Provider\Sdk\UserAgent\SystemInformation\AnalyticsProvider;
use Klevu\PhpSDK\Provider\UserAgentProviderInterface;
use PHPUnit\Framework\TestCase;

class AnalyticsProviderTest extends TestCase
{
    public function testIsInstanceOfInterface(): void
    {
        $analyticsProvider = new AnalyticsProvider();

        $this->assertInstanceOf(
            expected: UserAgentProviderInterface::class,
            actual: $analyticsProvider,
        );
    }

    public function testExecute_ComposerInstall(): void
    {
        if (!InstalledVersions::isInstalled('klevu/module-m2-analytics')) {
            $this->markTestSkipped('Module not installed by composer');
        }

        $analyticsProvider = new AnalyticsProvider();

        $result = $analyticsProvider->execute();

        $this->assertStringContainsString(
            needle: 'klevu-m2-analytics/' . $this->getLibraryVersion(),
            haystack: $result,
        );
    }

    public function testExecute_AppInstall(): void
    {
        if (InstalledVersions::isInstalled('klevu/module-m2-analytics')) {
            $this->markTestSkipped('Module installed by composer');
        }

        $analyticsProvider = new AnalyticsProvider();

        $result = $analyticsProvider->execute();

        $this->assertSame(
            expected: 'klevu-m2-analytics',
            actual: $result,
        );
    }

    /**
     * @return string
     */
    private function getLibraryVersion(): string
    {
        $composerFilename = __DIR__ . '/../../../../../../../composer.json';
        $composerContent = json_decode(
            json: file_get_contents($composerFilename) ?: '{}',
            associative: true,
        );
        if (!is_array($composerContent)) {
            $composerContent = [];
        }

        $version = $composerContent['version'] ?? '-';
        $versionParts = explode('.', $version) + array_fill(0, 4, '0');

        return implode('.', $versionParts);
    }
}