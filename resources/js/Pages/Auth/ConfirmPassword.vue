<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import FormField from '@/Components/Common/FormField.vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Head, useForm } from '@inertiajs/vue3'

const form = useForm({
    password: '',
})

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    })
}
</script>

<template>
    <GuestLayout>
        <Head title="Potwierdź hasło" />

        <p class="mb-4 text-sm text-muted-foreground">
            To jest zabezpieczony obszar aplikacji. Potwierdź swoje hasło, zanim
            przejdziesz dalej.
        </p>

        <form class="grid gap-4" @submit.prevent="submit">
            <FormField id="password" label="Hasło" :error="form.errors.password">
                <template #default="{ id, invalid, describedBy }">
                    <Input
                        :id="id"
                        type="password"
                        v-model="form.password"
                        required
                        autofocus
                        autocomplete="current-password"
                        :aria-invalid="invalid || undefined"
                        :aria-describedby="describedBy"
                    />
                </template>
            </FormField>

            <div class="flex items-center justify-end gap-4">
                <Button type="submit" :disabled="form.processing">Potwierdź</Button>
            </div>
        </form>
    </GuestLayout>
</template>
