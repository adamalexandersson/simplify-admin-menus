<?php

namespace SimplifyAdminMenus;

class ViteManifest
{
    private string $manifestPath;
    private ?array $manifest = null;

    public function __construct(string $manifestPath)
    {
        $this->manifestPath = $manifestPath;
    }

    public function getManifest(): array
    {
        if ($this->manifest === null) {
            if (!file_exists($this->manifestPath)) {
                return [];
            }

            $manifestContent = file_get_contents($this->manifestPath);
            $this->manifest = json_decode($manifestContent, true) ?? [];
        }

        return $this->manifest;
    }

    public function getAsset(string $name): ?string
    {
        $manifest = $this->getManifest();
        return $manifest[$name]['file'] ?? null;
    }

    public function getCss(string $name): array
    {
        $manifest = $this->getManifest();

        return $manifest[$name]['css'] ?? [];
    }

    public function getImports(string $name): array
    {
        $manifest = $this->getManifest();
        return $manifest[$name]['imports'] ?? [];
    }
} 
