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

        <div v-if="errors.access" class="mb-4 rounded-md bg-red-50 p-3 text-center text-sm font-medium text-red-600">
            {{ errors.access }}
        </div>

        <div class="flex flex-col gap-6">
            <Button as="a" :href="route('auth.login')" class="flex w-full items-center justify-center gap-2">
                <LogIn class="h-4 w-4" />
                Log in with Identity Provider
            </Button>

            <div class="text-center text-sm text-muted-foreground">Use your organization credentials to access the wiki</div>
        </div>
    </AuthBase>
</template>
