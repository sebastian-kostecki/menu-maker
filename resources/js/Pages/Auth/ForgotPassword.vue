<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import FormField from '@/Components/Common/FormField.vue'
import { Alert, AlertDescription } from '@/Components/ui/alert'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
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
    <GuestLayout>
        <Head title="Nie pamiętasz hasła?" />

        <p class="mb-4 text-sm text-muted-foreground">
            Nie pamiętasz hasła? Podaj swój adres e-mail, a wyślemy Ci link do
            resetu hasła, który pozwoli ustawić nowe.
        </p>

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

            <div class="flex items-center justify-end gap-4">
                <Button type="submit" :disabled="form.processing">
                    Wyślij link do resetu hasła
                </Button>
            </div>
        </form>
    </GuestLayout>
</template>
