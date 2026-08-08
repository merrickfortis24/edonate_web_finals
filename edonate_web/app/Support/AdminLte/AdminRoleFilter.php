<?php

namespace App\Support\AdminLte;

use ColorlibHQ\AdminLte\Menu\Filters\FilterInterface;

class AdminRoleFilter implements FilterInterface
{
    /**
     * Keep navigation visibility aligned with the existing session-based
     * admin/staff middleware without changing backend authorization.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    public function transform(array $item): ?array
    {
        $role = strtolower(trim((string) session('admin_role', '')));

        if (isset($item['roles']) && ! in_array($role, (array) $item['roles'], true)) {
            return null;
        }

        unset($item['roles']);

        if (isset($item['active_routes'])) {
            $item['active'] = request()->routeIs(...(array) $item['active_routes']);
            unset($item['active_routes']);
        }

        if (isset($item['submenu'])) {
            $children = [];

            foreach ($item['submenu'] as $child) {
                $filteredChild = $this->transform($child);

                if ($filteredChild !== null) {
                    $children[] = $filteredChild;
                }
            }

            if ($children === []) {
                return null;
            }

            $item['submenu'] = $children;
        }

        return $item;
    }
}
