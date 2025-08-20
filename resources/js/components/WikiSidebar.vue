<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import NavUser from '@/components/NavUser.vue';
import { 
    Sidebar, 
    SidebarContent, 
    SidebarFooter, 
    SidebarHeader, 
    SidebarMenu, 
    SidebarMenuButton, 
    SidebarMenuItem,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarGroupContent
} from '@/components/ui/sidebar';
import { type TeamNamespace } from '@/types';
import { Link, usePage, useForm, router } from '@inertiajs/vue3';
import { 
    Star, 
    Clock, 
    Users, 
    FolderOpen,
    X,
    GripVertical,
    Plus,
    Loader2
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import draggable from 'vuedraggable';

interface Favorite {
    id: number;
    page_id: string;
    page_title: string;
    page_url: string;
    sort_order: number;
    created_at: string;
    updated_at: string;
}

interface WikiPage {
    id: string;
    title: string;
    namespace: string;
    href: string;
    lastViewed?: Date;
    isFavorite?: boolean;
}


const page = usePage();

const favorites = ref<Favorite[]>([]);
const recentlyViewed = ref<WikiPage[]>([]);
const showTeamSelector = ref(false);
const showArchivedEFs = ref(false);

// Get teams and user teams from shared props
const allTeams = computed(() => page.props.teams || []);
const userTeamsFromProps = computed(() => page.props.userTeams || []);
const currentEF = computed(() => page.props.currentEF || null);
const archivedEFs = computed(() => page.props.archivedEFs || []);

// Local reactive copy for drag and drop
const userTeams = ref<TeamNamespace[]>([]);

// Watch for changes in props and update local copy
watch(userTeamsFromProps, (newTeams) => {
    userTeams.value = [...newTeams];
}, { immediate: true });

// Available teams for selection (not already in user's list)
const userTeamNames = computed(() => userTeams.value.map(t => t.name));
const availableTeams = computed(() => 
    allTeams.value.filter(team => !userTeamNames.value.includes(team.name))
);


const condensedRecent = computed(() => 
    recentlyViewed.value.slice(0, 5).map(page => ({
        title: page.title.length > 20 ? `${page.title.substring(0, 20)}...` : page.title,
        href: page.href,
        icon: Clock
    }))
);

const loadFavorites = async () => {
    try {
        const response = await fetch('/api/favorites', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            }
        });
        if (response.ok) {
            favorites.value = await response.json();
        }
    } catch (error) {
        console.error('Failed to load favorites:', error);
    }
};

const loadRecentlyViewed = () => {
    const stored = localStorage.getItem('wiki_recent_pages');
    if (stored) {
        recentlyViewed.value = JSON.parse(stored).map((item: any) => ({
            ...item,
            visitedAt: new Date(item.visitedAt)
        }));
    }
};

const addTeamToUser = (team: TeamNamespace) => {
    addTeamForm.team_name = team.name;
    addTeamForm.team_display_name = team.displayName;
    addTeamForm.team_href = team.href;
    addTeamForm.team_type = team.type || 'namespace';
    
    addTeamForm.post('/api/user-teams', {
        preserveScroll: true,
        onSuccess: () => {
            // Hide selector if no more teams available
            if (availableTeams.value.length === 1) {
                showTeamSelector.value = false;
            }
        },
        onError: (errors) => {
            console.error('Failed to add team:', errors);
        }
    });
};

const removeTeamFromUser = (teamId: number) => {
    router.delete(`/api/user-teams/${teamId}`, {
        preserveScroll: true,
        onError: (errors) => {
            console.error('Failed to remove team:', errors);
        }
    });
};

// Forms for Inertia operations
const reorderForm = useForm({
    favorites: [] as Array<{id: number, sort_order: number}>
});

const reorderTeamsForm = useForm({
    teams: [] as Array<{id: number, sort_order: number}>
});

const addTeamForm = useForm({
    team_name: '',
    team_display_name: '',
    team_href: '',
    team_type: 'namespace'
});

// Drag and drop functionality for user teams
const onUserTeamsReorder = () => {
    const reorderedTeams = userTeams.value.map((team, index) => ({
        id: team.id,
        sort_order: index
    }));

    reorderTeamsForm.teams = reorderedTeams;
    
    reorderTeamsForm.post('/api/user-teams/reorder', {
        preserveScroll: true,
        onError: (errors) => {
            console.error('Failed to reorder user teams:', errors);
        }
    });
};

// Drag and drop functionality for favorites
const onFavoritesReorder = () => {
    const reorderedFavorites = favorites.value.map((fav, index) => ({
        id: fav.id,
        sort_order: index
    }));

    reorderForm.favorites = reorderedFavorites;
    
    reorderForm.post('/api/favorites/reorder', {
        preserveScroll: true,
        onError: (errors) => {
            console.error('Failed to reorder favorites:', errors);
            // Revert order if failed
            loadFavorites();
        }
    });
};

// Remove favorite functionality
const removeFavorite = (favoriteId: number) => {
    router.delete(`/api/favorites/${favoriteId}`, {
        preserveScroll: true,
        onSuccess: () => {
            loadFavorites();
        },
        onError: (errors) => {
            console.error('Failed to remove favorite:', errors);
        }
    });
};

onMounted(() => {
    loadFavorites();
    loadRecentlyViewed();
    
    // Listen for favorites updates from page component
    window.addEventListener('favorites-updated', loadFavorites);
    
    // Listen for recent pages updates from page component
    window.addEventListener('recent-pages-updated', loadRecentlyViewed);
    
    // Listen for storage changes to update recent pages (for other tabs)
    window.addEventListener('storage', (e) => {
        if (e.key === 'wiki_recent_pages') {
            loadRecentlyViewed();
        }
    });
});

// Cleanup event listeners
onUnmounted(() => {
    window.removeEventListener('favorites-updated', loadFavorites);
    window.removeEventListener('recent-pages-updated', loadRecentlyViewed);
    window.removeEventListener('storage', loadRecentlyViewed);
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')" prefetch>
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="gap-0">
            <!-- Favorites Section -->
            <SidebarGroup class="px-2 py-2">
                <SidebarGroupLabel class="text-xs font-medium text-sidebar-foreground/70">
                    <Star class="mr-2 size-3" />
                    Favorites
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <draggable 
                            v-model="favorites" 
                            @end="onFavoritesReorder"
                            item-key="id"
                            group="favorites"
                            handle=".drag-handle"
                            class="space-y-0"
                        >
                            <template #item="{ element: favorite }">
                                <SidebarMenuItem class="py-0 group">
                                    <div class="flex items-center gap-1 h-7">
                                        <button 
                                            class="drag-handle opacity-0 group-hover:opacity-100 p-1 hover:bg-sidebar-accent rounded transition-opacity cursor-grab active:cursor-grabbing"
                                            title="Drag to reorder"
                                        >
                                            <GripVertical class="h-3 w-3 text-sidebar-foreground/50" />
                                        </button>
                                        
                                        <SidebarMenuButton 
                                            as-child 
                                            size="sm" 
                                            class="h-6 text-xs flex-1 min-w-0"
                                        >
                                            <Link :href="favorite.page_url" prefetch class="flex items-center gap-2">
                                                <span class="truncate">{{ favorite.page_title }}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                        
                                        <button 
                                            @click="removeFavorite(favorite.id)"
                                            class="opacity-0 group-hover:opacity-100 p-1 hover:bg-sidebar-accent hover:text-red-600 rounded transition-opacity"
                                            title="Remove from favorites"
                                        >
                                            <X class="h-3 w-3" />
                                        </button>
                                    </div>
                                </SidebarMenuItem>
                            </template>
                        </draggable>
                        
                        <SidebarMenuItem v-if="favorites.length === 0" class="py-0">
                            <div class="px-2 py-1 text-xs text-sidebar-foreground/50">
                                No favorites yet
                            </div>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <!-- Recently Viewed Section -->
            <SidebarGroup class="px-2 py-2">
                <SidebarGroupLabel class="text-xs font-medium text-sidebar-foreground/70">
                    <Clock class="mr-2 size-3" />
                    Recent
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in condensedRecent" :key="item.href" class="py-0">
                            <SidebarMenuButton as-child size="sm" class="h-7 text-xs">
                                <Link :href="item.href" prefetch class="flex items-center gap-2">
                                    <span class="truncate">{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem v-if="recentlyViewed.length === 0" class="py-0">
                            <div class="px-2 py-1 text-xs text-sidebar-foreground/50">
                                No recent pages
                            </div>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <!-- Current Eurofurence Section -->
            <SidebarGroup v-if="currentEF" class="px-2 py-2">
                <SidebarGroupLabel class="text-xs font-medium text-sidebar-foreground/70">
                    <Star class="mr-2 size-3" />
                    Current Eurofurence
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem class="py-0">
                            <SidebarMenuButton as-child size="sm" class="h-7 text-xs">
                                <Link :href="currentEF.href" prefetch class="flex items-center gap-2">
                                    <span class="truncate">{{ currentEF.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <!-- Archived Eurofurences Section -->
            <SidebarGroup v-if="archivedEFs.length > 0" class="px-2 py-2">
                <SidebarGroupLabel class="text-xs font-medium text-sidebar-foreground/70">
                    <Clock class="mr-2 size-3" />
                    Archived Eurofurences
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <!-- Show/Hide toggle for archived EFs -->
                        <SidebarMenuItem class="py-0">
                            <SidebarMenuButton 
                                size="sm" 
                                class="h-6 text-xs text-sidebar-foreground/60 hover:text-sidebar-foreground"
                                @click="showArchivedEFs = !showArchivedEFs"
                            >
                                <span v-if="!showArchivedEFs">+ Show archived ({{ archivedEFs.length }})</span>
                                <span v-else>- Hide archived</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        
                        <!-- Archived EF list (collapsible) -->
                        <template v-if="showArchivedEFs">
                            <SidebarMenuItem v-for="ef in archivedEFs" :key="ef.id" class="py-0">
                                <SidebarMenuButton as-child size="sm" class="h-7 text-xs">
                                    <Link :href="ef.href" prefetch class="flex items-center gap-2">
                                        <span class="truncate">{{ ef.title }}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </template>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <!-- Teams Section -->
            <SidebarGroup class="px-2 py-2">
                <SidebarGroupLabel class="text-xs font-medium text-sidebar-foreground/70">
                    <Users class="mr-2 size-3" />
                    Teams
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <!-- User's Teams (Draggable) -->
                        <template v-if="userTeams.length > 0">
                            <draggable
                                v-model="userTeams"
                                group="user-teams"
                                @end="onUserTeamsReorder"
                                item-key="id"
                                handle=".drag-handle"
                                tag="div"
                            >
                                <template #item="{ element: team }">
                                    <SidebarMenuItem :key="team.id" class="py-0">
                                        <div class="flex items-center gap-2 w-full h-7 px-2 text-xs group hover:bg-sidebar-accent rounded-sm">
                                            <GripVertical class="drag-handle size-3 text-sidebar-foreground/40 opacity-0 group-hover:opacity-100 cursor-move flex-shrink-0" />
                                            <Link :href="team.href" prefetch class="flex items-center gap-2 flex-1 min-w-0">
                                                <FolderOpen class="size-3 text-sidebar-foreground/60 flex-shrink-0" />
                                                <span class="truncate">{{ team.displayName }}</span>
                                            </Link>
                                            <button
                                                @click.stop="removeTeamFromUser(team.id)"
                                                class="opacity-0 group-hover:opacity-100 p-0.5 hover:bg-sidebar-accent rounded-xs transition-opacity"
                                                title="Remove from teams"
                                            >
                                                <X class="size-3 text-sidebar-foreground/60" />
                                            </button>
                                        </div>
                                    </SidebarMenuItem>
                                </template>
                            </draggable>
                        </template>
                        
                        <!-- Add Teams Button -->
                        <template v-if="availableTeams.length > 0">
                            <SidebarMenuItem class="py-0">
                                <SidebarMenuButton 
                                    size="sm" 
                                    class="h-6 text-xs text-sidebar-foreground/60 hover:text-sidebar-foreground"
                                    @click="showTeamSelector = !showTeamSelector"
                                >
                                    <span v-if="!showTeamSelector">+ Add teams</span>
                                    <span v-else>- Hide teams</span>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </template>
                        
                        <!-- Team Selector -->
                        <template v-if="showTeamSelector && availableTeams.length > 0">
                            <SidebarMenuItem v-for="team in availableTeams" :key="team.name" class="py-0">
                                <div class="flex items-center justify-between w-full h-7 px-2 text-xs group hover:bg-sidebar-accent rounded-sm">
                                    <div class="flex items-center flex-1 min-w-0">
                                        <FolderOpen class="size-3 text-sidebar-foreground/60 mr-2 flex-shrink-0" />
                                        <span class="truncate">{{ team.displayName }}</span>
                                    </div>
                                    <button
                                        @click="addTeamToUser(team)"
                                        :disabled="addTeamForm.processing"
                                        class="p-0.5 hover:bg-sidebar-accent hover:scale-110 rounded-xs transition-all duration-200 flex-shrink-0 group/button"
                                        title="Add team"
                                    >
                                        <Plus 
                                            v-if="!addTeamForm.processing"
                                            class="size-3 text-sidebar-foreground/60 group-hover/button:text-sidebar-foreground group-hover/button:rotate-90 transition-all duration-200" 
                                        />
                                        <Loader2 
                                            v-else
                                            class="size-3 text-sidebar-foreground/60 animate-spin" 
                                        />
                                    </button>
                                </div>
                            </SidebarMenuItem>
                        </template>
                        
                        <!-- Empty State -->
                        <template v-if="userTeams.length === 0">
                            <SidebarMenuItem class="py-0">
                                <div class="px-2 py-1 text-xs text-sidebar-foreground/50">
                                    No teams selected
                                </div>
                            </SidebarMenuItem>
                        </template>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
</template>