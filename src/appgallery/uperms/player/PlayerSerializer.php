<?php

declare(strict_types=1);

namespace appgallery\uperms\player;

use appgallery\uperms\permission\node\Node;

final class PlayerSerializer{

    /**
     * @return array<string, mixed>
     */
    public static function serialize(PlayerSession $session): array{
        return [
            'xuid' => $session->getXuid(),
            'username' => $session->getUsername(),
            'groups' => $session->getGroups(),
            'permissions' => array_map(
                fn(Node $n) => $n->serialize(),
                $session->getPersonalNodes()
            ),
            'prefix' => $session->getPrefix() !== '' ? $session->getPrefix() : null,
            'suffix' => $session->getSuffix() !== '' ? $session->getSuffix() : null,
            'meta' => $session->getAllMeta(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function deserializeInto(PlayerSession $session, array $data): void{
        $session->setGroups((array)($data['groups'] ?? []));

        $nodes = array_map(
            fn(array $raw) => Node::deserialize($raw),
            (array)($data['permissions'] ?? [])
        );
        $session->setPersonalNodes($nodes);

        $session->setPrefix((string)($data['prefix'] ?? ''));
        $session->setSuffix((string)($data['suffix'] ?? ''));
        $session->setMetaMap((array)($data['meta'] ?? []));
        $session->markClean();
    }
}
