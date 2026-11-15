<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import FormField from '@/Components/Common/FormField.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Head, useForm } from '@inertiajs/vue3'

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
})

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <GuestLayout>
        <Head title="Reset hasła" />

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

            <FormField id="password" label="Nowe hasło" :error="form.errors.password">
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

            <div class="flex items-center justify-end gap-4">
                <Button type="submit" :disabled="form.processing">Zresetuj hasło</Button>
            </div>
        </form>
    </GuestLayout>
</template>
