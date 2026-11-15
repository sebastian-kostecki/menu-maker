<script setup>
import FormField from '@/Components/Common/FormField.vue'
import { Button } from '@/Components/ui/button'
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const passwordInput = ref(null)
const currentPasswordInput = ref(null)

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation')
                passwordInput.value.$el.focus()
            }
            if (form.errors.current_password) {
                form.reset('current_password')
                currentPasswordInput.value.$el.focus()
            }
        },
    })
}
</script>

<template>
    <Card role="region" aria-labelledby="update-password-title">
        <CardHeader>
            <CardTitle id="update-password-title" as="h2">Zmiana hasła</CardTitle>
            <CardDescription>
                Używaj długiego, losowego hasła, aby zabezpieczyć swoje konto.
            </CardDescription>
        </CardHeader>

        <CardContent>
            <form class="grid gap-4" @submit.prevent="updatePassword">
                <FormField
                    id="current_password"
                    label="Obecne hasło"
                    :error="form.errors.current_password"
                >
                    <template #default="{ id, invalid, describedBy }">
                        <Input
                            :id="id"
                            ref="currentPasswordInput"
                            type="password"
                            v-model="form.current_password"
                            autocomplete="current-password"
                            :aria-invalid="invalid || undefined"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <FormField
                    id="password"
                    label="Nowe hasło"
                    :error="form.errors.password"
                >
                    <template #default="{ id, invalid, describedBy }">
                        <Input
                            :id="id"
                            ref="passwordInput"
                            type="password"
                            v-model="form.password"
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
                            autocomplete="new-password"
                            :aria-invalid="invalid || undefined"
                            :aria-describedby="describedBy"
                        />
                    </template>
                </FormField>

                <div class="flex items-center gap-4">
                    <Button type="submit" :disabled="form.processing">
                        Zapisz
                    </Button>

                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-if="form.recentlySuccessful"
                            class="text-sm text-muted-foreground"
                        >
                            Zapisano.
                        </p>
                    </Transition>
                </div>
            </form>
        </CardContent>
    </Card>
</template>
