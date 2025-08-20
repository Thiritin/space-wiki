<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { LogIn } from 'lucide-vue-next';

const page = usePage();
const errors = page.props.errors as Record<string, string>;
</script>

<template>
    <AuthBase title="Access Wiki" description="Log in with your identity provider to access the wiki">
        <Head title="Log in" />

        <div v-if="errors.access" class="mb-4 text-sm font-medium text-center text-red-600 bg-red-50 p-3 rounded-md">
            {{ errors.access }}
        </div>

        <div class="flex flex-col gap-6">
            <Button 
                as="a" 
                :href="route('auth.login')"
                class="w-full flex items-center justify-center gap-2"
            >
                <LogIn class="w-4 h-4" />
                Log in with Identity Provider
            </Button>

            <div class="text-sm text-center text-muted-foreground">
                Use your organization credentials to access the wiki
            </div>
        </div>
    </AuthBase>
</template>
