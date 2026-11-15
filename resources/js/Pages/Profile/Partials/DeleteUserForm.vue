<script setup>
import ConfirmDialog from '@/Components/Common/ConfirmDialog.vue';
import FormField from '@/Components/Common/FormField.vue';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const closeDialog = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};

const deleteUser = () => {
    if (form.processing) {
        return;
    }

    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
        onError: () => passwordInput.value?.$el.focus(),
        onFinish: () => form.reset(),
    });
};

const handleOpenChange = (value) => {
    if (value) {
        confirmingUserDeletion.value = true;

        return;
    }

    closeDialog();
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle as="h2">Usuń konto</CardTitle>
            <CardDescription>
                Po usunięciu konta wszystkie jego zasoby i dane zostaną trwale
                usunięte. Przed usunięciem konta zapisz wszystkie dane, które
                chcesz zachować.
            </CardDescription>
        </CardHeader>

        <CardContent>
            <Button
                type="button"
                variant="destructive"
                @click="confirmingUserDeletion = true"
            >
                Usuń konto
            </Button>

            <ConfirmDialog
                :open="confirmingUserDeletion"
                title="Czy na pewno chcesz usunąć swoje konto?"
                description="Po usunięciu konta wszystkie jego zasoby i dane zostaną trwale usunięte. Wpisz swoje hasło, aby potwierdzić, że chcesz trwale usunąć konto."
                confirm-label="Usuń konto"
                variant="destructive"
                :processing="form.processing"
                @update:open="handleOpenChange"
                @confirm="deleteUser"
            >
                <FormField
                    id="delete-account-password"
                    label="Hasło"
                    :error="form.errors.password"
                >
                    <template #default="{ id, invalid, describedBy }">
                        <Input
                            :id="id"
                            ref="passwordInput"
                            type="password"
                            v-model="form.password"
                            autocomplete="current-password"
                            :aria-invalid="invalid || undefined"
                            :aria-describedby="describedBy"
                            @keydown.enter.prevent="deleteUser"
                        />
                    </template>
                </FormField>
            </ConfirmDialog>
        </CardContent>
    </Card>
</template>
