<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Activity, BookOpen, Calendar, ChartBar, Database, FolderGit2, LayoutGrid, MessagesSquare, Star, User, UserCheck } from 'lucide-vue-next';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
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
import { edit as profileEdit } from '@/routes/profile';
import { index as reportsIndex } from '@/routes/reports';
import { index as backupsIndex } from '@/routes/backups';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
        icon: LayoutGrid,
    },
    {
        title: 'Attendance',
        href: '/manage-attendance',
        icon: UserCheck,
    },
    {
        title: 'Reports',
        href: reportsIndex().url,
        icon: ChartBar,
    },
    {
        title: 'Profile',
        href: profileEdit().url,
        icon: User,
    },
    {
        title: 'Backups',
        href: backupsIndex().url,
        icon: Database,
    },
    {
        title: 'Activity Logs',
        href: '/activity-logs',
        icon: Activity,
    },
    {
        title: 'Calendar',
        href: '/calendar',
        icon: Calendar,
    },
];

// const footerNavItems: NavItem[] = [
//     {
//         title: 'Repository',
//         href: 'https://github.com/SorenOutis/qrcode-attendance-mk2',
//         icon: FolderGit2,
//     },
// ];
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
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
