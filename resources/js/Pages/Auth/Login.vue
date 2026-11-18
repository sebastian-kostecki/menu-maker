<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import TextLink from '@/Components/Common/TextLink.vue'
import FormField from '@/Components/Common/FormField.vue'
import { Alert, AlertDescription } from '@/Components/ui/alert'
import { Button } from '@/Components/ui/button'
import { Checkbox } from '@/Components/ui/checkbox'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { ArrowRight } from 'lucide-vue-next'
import { Head, useForm } from '@inertiajs/vue3'

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
})

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <GuestLayout title="Logowanie" description="Witaj ponownie! Zaloguj się, żeby zaplanować tydzień.">
        <Head title="Logowanie" />

        <Alert v-if="status" variant="success">
            <AlertDescription>{{ status }}</AlertDescription>
        </Alert>

        <form class="grid gap-4" @submit.prevent="submit">
            <FormField id="email" label="Adres e-mail" :error="form.errors.email">
                <template #default="{ id, invalid, describedBy }">
                    <Input
                        :id="id"
                        type="email"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        :aria-invalid="invalid || undefined"
                        :aria-describedby="describedBy"
                    />
                </template>
            </FormField>

            <FormField id="password" label="Hasło" :error="form.errors.password">
                <template #default="{ id, invalid, describedBy }">
                    <Input
                        :id="id"
                        type="password"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        :aria-invalid="invalid || undefined"
                        :aria-describedby="describedBy"
                    />
                </template>
            </FormField>

            <div class="flex items-center gap-2">
                <Checkbox id="remember" v-model="form.remember" />
                <Label for="remember">Zapamiętaj mnie</Label>
            </div>

            <Button type="submit" size="lg" class="w-full" :disabled="form.processing">
                Zaloguj się
                <ArrowRight aria-hidden="true" />
            </Button>

            <div class="flex flex-wrap justify-between gap-3 text-sm">
                <TextLink v-if="canResetPassword" :href="route('password.request')">
                    Nie pamiętasz hasła?
                </TextLink>

                <span class="inline-flex gap-1">
                    <span class="text-muted-foreground">Nie masz konta?</span>
                    <TextLink :href="route('register')">Załóż konto</TextLink>
                </span>
            </div>
        </form>
    </GuestLayout>
</template>
