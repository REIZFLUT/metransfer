import { Form, Head } from '@inertiajs/react';
import { LockKeyhole } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { unlock } from '@/routes/public/transfers';

type Props = {
    token: string;
    sender: string;
};

export default function PublicTransferPassword({ token, sender }: Props) {
    return (
        <>
            <Head title="Passwort erforderlich" />

            <Card className="mx-auto max-w-sm">
                <CardHeader className="text-center">
                    <div className="mx-auto mb-2 flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <LockKeyhole className="size-7" />
                    </div>
                    <CardTitle className="text-xl">Passwortgeschützt</CardTitle>
                    <CardDescription>
                        {sender} hat diesen Transfer mit einem Passwort
                        geschützt.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        {...unlock.form(token)}
                        resetOnError
                        className="grid gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="password">Passwort</Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoFocus
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.password} />
                                </div>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Entsperren
                                </Button>
                            </>
                        )}
                    </Form>
                </CardContent>
            </Card>
        </>
    );
}
