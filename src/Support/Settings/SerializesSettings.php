<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Settings;

use Illuminate\Support\Collection;

/**
 * @property SettingsSerializer $serializer
 */
trait SerializesSettings
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->serializer->toArray($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
    }

    /**
     * @return Collection<string, mixed>
     */
    public function toCollection(): Collection
    {
        return $this->serializer->toCollection($this);
    }

    /**
     * Return only settings already hydrated without triggering lazy loads.
     *
     * @return array<string, mixed>
     */
    public function onlyLoaded(): array
    {
        return $this->serializer->onlyLoaded($this);
    }
}
