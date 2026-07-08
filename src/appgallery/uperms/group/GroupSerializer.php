<?php

declare(strict_types=1);

namespace appgallery\uperms\group;

/**
 * Convierte grupos a/desde el formato YAML del storage.
 * Mantiene la lógica de serialización fuera de Group y del Storage.
 */
final class GroupSerializer{

    /**
     * Convierte el array crudo del YAML a un objeto Group.
     *
     * Formato esperado del YAML:
     *
     * youtuber:
     *   displayName: "§cYouTuber"
     *   weight: 65
     *   parent: vip
     *   permissions:
     *     - node: "essentials.fly"
     *       state: true
     *       expiresAt: ~
     *       context: {}
     *   prefix: "§7[§cYT§7] "
     *   suffix: ""
     *   meta:
     *     max-homes: "10"
     */
    public static function fromYaml(string $id, array $raw): Group{
        // Normalizar los nodos del YAML al formato de Node::deserialize
        $permissions = [];
        foreach((array)($raw['permissions'] ?? []) as $entry){
            $permissions[] = [
                'permission' => (string)($entry['node'] ?? $entry['permission'] ?? ''),
                'state' => (bool)($entry['state'] ?? true),
                'expiresAt' => isset($entry['expiresAt'])
                    ? (int)$entry['expiresAt']
                    : null,
                'context' => (array)($entry['context'] ?? []),
            ];
        }

        return Group::deserialize([
            'id' => $id,
            'displayName' => $raw['displayName'] ?? $id,
            'weight' => $raw['weight'] ?? 0,
            'parent' => $raw['parent'] ?? null,
            'permissions' => $permissions,
            'prefix' => $raw['prefix'] ?? '',
            'suffix' => $raw['suffix'] ?? '',
            'meta' => $raw['meta'] ?? [],
        ]);
    }

    /**
     * Convierte un Group al formato YAML para persistir.
     *
     * @return array<string, mixed>
     */
    public static function toYaml(Group $group): array{
        $serialized = $group->serialize();
        $permissions = [];

        foreach($serialized['permissions'] as $node){
            $permissions[] = array_filter([
                'node' => $node['permission'],
                'state' => $node['state'],
                'expiresAt' => $node['expiresAt'],
                'context' => !empty($node['context']) ? $node['context'] : null,
            ], fn($v) => $v !== null);
        }

        return array_filter([
            'displayName' => $serialized['displayName'],
            'weight' => $serialized['weight'],
            'parent' => $serialized['parent'] ?: null,
            'permissions' => $permissions ?: null,
            'prefix' => $serialized['prefix'],
            'suffix' => $serialized['suffix'],
            'meta' => $serialized['meta'] ?: null,
        ], fn($v) => $v !== null);
    }
}