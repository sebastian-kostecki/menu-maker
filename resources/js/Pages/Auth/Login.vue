<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import FormField from '@/Components/Common/FormField.vue'
import { Alert, AlertDescription } from '@/Components/ui/alert'
import { Button } from '@/Components/ui/button'
import { Checkbox } from '@/Components/ui/checkbox'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Head, Link, useForm } from '@inertiajs/vue3'

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
    <GuestLayout>
        <Head title="Logowanie" />

        <Alert v-if="status" variant="success" class="mb-4">
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

            <div class="flex items-center justify-end gap-4">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="rounded-md text-sm text-muted-foreground underline transition-colors hover:text-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                >
                    Nie pamiętasz hasła?
                </Link>

                <Button type="submit" :disabled="form.processing">Zaloguj się</Button>
            </div>
        </form>
    </GuestLayout>
</template>
