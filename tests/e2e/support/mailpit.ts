const mailpitUrl = (): string => process.env.MAILPIT_URL ?? 'http://localhost:8025';

const sleep = (ms: number): Promise<void> => new Promise((resolve) => setTimeout(resolve, ms));

type MailpitMessage = { ID: string; Subject: string };

const findMessage = async (email: string): Promise<MailpitMessage> => {
  const query = encodeURIComponent(`to:"${email}"`);

  for (let attempt = 0; attempt < 20; attempt++) {
    const response = await fetch(`${mailpitUrl()}/api/v1/search?query=${query}`);

    if (response.ok) {
      const { messages } = (await response.json()) as { messages: MailpitMessage[] };

      if (messages.length > 0) {
        return messages[0];
      }
    }

    await sleep(500);
  }

  throw new Error(`No e-mail for ${email} appeared in Mailpit`);
};

export const findResetMessage = findMessage;

export const findResetPath = async (email: string): Promise<string> => {
  const message = await findMessage(email);
  const response = await fetch(`${mailpitUrl()}/api/v1/message/${message.ID}`);
  const { Text, HTML } = (await response.json()) as { Text: string; HTML: string };
  const body = `${Text}\n${HTML}`.replace(/&amp;/g, '&');
  const match = body.match(/\/reset-password\/[^\s"'<>)\]]+/);

  if (!match) {
    throw new Error(`No reset link found in the e-mail for ${email}`);
  }

  return match[0];
};
