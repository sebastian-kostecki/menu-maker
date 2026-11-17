<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import TextLink from '@/Components/Common/TextLink.vue'
import FormField from '@/Components/Common/FormField.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Head, useForm } from '@inertiajs/vue3'

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <GuestLayout
        title="Rejestracja"
        description="Konto jest darmowe. Przepisy zostają tylko u Ciebie."
        slogan="Trzy posiłki dziennie, zero zgadywania przy sklepie."
    >
        <Head title="Rejestracja" />

        <form class="grid gap-4" @submit.prevent="submit">
            <FormField id="name" label="Imię" :error="form.errors.name">
                <template #default="{ id, invalid, describedBy }">
                    <Input
                        :id="id"
                        type="text"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                        :aria-invalid="invalid || undefined"
                        :aria-describedby="describedBy"
                    />
                </template>
            </FormField>

            <FormField id="email" label="Adres e-mail" :error="form.errors.email">
                <template #default="{ id, invalid, describedBy }">
                    <Input
                        :id="id"
                        type="email"
                        v-model="form.email"
                        required
                        autocomplete="username"
                        :aria-invalid="invalid || undefined"
                        :aria-describedby="describedBy"
                    />
                </template>
            </FormField>

            <div class="grid gap-4 sm:grid-cols-2">
            <FormField id="password" label="Hasło" :error="form.errors.password">
                <template #default="{ id, invalid, describedBy }">
                    <Input
                        :id="id"
                        type="password"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        :aria-invalid="invalid || undefined"
                        :aria-describedby="describedBy"
                    />
                </template>
            </FormField>

            <FormField
                id="password_confirmation"
                label="Potwierdź hasło"
                :error="form.errors.password_confirmation"
            >
                <template #default="{ id, invalid, describedBy }">
                    <Input
                        :id="id"
                        type="password"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        :aria-invalid="invalid || undefined"
                        :aria-describedby="describedBy"
                    />
                </template>
            </FormField>
            </div>

            <Button type="submit" size="lg" class="w-full" :disabled="form.processing">
                Zarejestruj się
            </Button>

            <p class="flex gap-1 text-sm">
                <span class="text-muted-foreground">Masz już konto?</span>
                <TextLink :href="route('login')">Zaloguj się</TextLink>
            </p>
        </form>
    </GuestLayout>
</template>
