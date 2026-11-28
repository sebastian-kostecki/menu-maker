<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Button } from '@/Components/ui/button'
import { Head } from '@inertiajs/vue3'

defineProps({
    client_name: { type: String, required: true },
    client_id: { type: String, required: true },
    scopes: { type: Array, required: true },
    auth_token: { type: String, required: true },
    state: { type: String, default: null },
    csrf_token: { type: String, required: true },
})
</script>

<template>
    <GuestLayout
        title="Dostęp do konta"
        :description="`Aplikacja ${client_name} prosi o dostęp do Twojego konta Menu Maker`"
    >
        <Head title="Dostęp do konta" />

        <ul class="grid list-disc gap-1 pl-5">
            <li v-for="scope in scopes" :key="scope">{{ scope }}</li>
        </ul>

        <!-- Native forms: both answers are a 302 to the client's redirect_uri, which an Inertia visit cannot follow. -->
        <div class="grid gap-3">
            <form method="post" :action="route('passport.authorizations.approve')">
                <input type="hidden" name="_token" :value="csrf_token" />
                <input type="hidden" name="state" :value="state ?? ''" />
                <input type="hidden" name="client_id" :value="client_id" />
                <input type="hidden" name="auth_token" :value="auth_token" />

                <Button type="submit" size="lg" class="w-full">Zezwól</Button>
            </form>

            <form method="post" :action="route('passport.authorizations.deny')">
                <input type="hidden" name="_method" value="DELETE" />
                <input type="hidden" name="_token" :value="csrf_token" />
                <input type="hidden" name="state" :value="state ?? ''" />
                <input type="hidden" name="client_id" :value="client_id" />
                <input type="hidden" name="auth_token" :value="auth_token" />

                <Button type="submit" variant="outline" size="lg" class="w-full">Odmów</Button>
            </form>
        </div>
    </GuestLayout>
</template>
