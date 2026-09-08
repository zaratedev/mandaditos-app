<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChartColumn, LayoutGrid, ShoppingBag, Users } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Panel',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    const role = page.props.auth.user.role as string;

    if (role === 'admin') {
        items.push(
            { title: 'Pedidos', href: '/orders', icon: ShoppingBag },
            { title: 'Clientes', href: '/clients', icon: Users },
            { title: 'Reportes', href: '/reports', icon: ChartColumn },
        );
    } else if (role === 'courier') {
        items.push({ title: 'Mis pedidos', href: '/board', icon: ShoppingBag });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
