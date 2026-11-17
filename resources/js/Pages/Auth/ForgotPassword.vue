<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import TextLink from '@/Components/Common/TextLink.vue'
import FormField from '@/Components/Common/FormField.vue'
import { Alert, AlertDescription } from '@/Components/ui/alert'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { ArrowLeft } from 'lucide-vue-next'
import { Head, useForm } from '@inertiajs/vue3'

defineProps({
    status: {
        type: String,
    },
})

const form = useForm({
    email: '',
})

const submit = () => {
    form.post(route('password.email'))
}
</script>

<template>
    <GuestLayout
        title="Nie pamiętasz hasła?"
        description="Podaj swój adres e-mail, a wyślemy Ci link do resetu hasła, który pozwoli ustawić nowe."
    >
        <Head title="Nie pamiętasz hasła?" />

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

            <Button type="submit" size="lg" class="w-full" :disabled="form.processing">
                Wyślij link do resetu hasła
            </Button>

            <p class="text-sm">
                <TextLink :href="route('login')" class="inline-flex items-center gap-2">
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    Wróć do logowania
                </TextLink>
            </p>
        </form>
    </GuestLayout>
</template>
