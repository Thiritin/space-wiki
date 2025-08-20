<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import WikiSidebar from '@/components/WikiSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { useWikiLinkInterceptor } from '@/composables/useWikiLinkInterceptor';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

// Enable wiki link interception globally
useWikiLinkInterceptor();
</script>

<template>
    <AppShell variant="sidebar">
        <WikiSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
    </AppShell>
</template>
