const mailpitUrl = (): string => process.env.MAILPIT_URL ?? 'http://localhost:8025';

const sleep = (ms: number): Promise<void> => new Promise((resolve) => setTimeout(resolve, ms));

type MailpitMessage = { ID: string; Subject: string };

export const generateRandomEmail = (): string => {
  const random = Math.random().toString(36).slice(2, 8);
  return `e2e-${random}@example.com`;
};

export const findResetMessage = async (email: string): Promise<MailpitMessage> => {
  const query = encodeURIComponent(`to:"${email}"`);

  for (let attempt = 0; attempt < 20; attempt++) {
    try {
      const response = await fetch(`${mailpitUrl()}/api/v1/search?query=${query}`);

      if (response.ok) {
        const { messages } = (await response.json()) as { messages: MailpitMessage[] };

        if (messages.length > 0) {
          return messages[0];
        }
      }
    } catch {
      // Mailpit not reachable yet; retry until the deadline below
    }

    await sleep(500);
  }

  throw new Error(`No e-mail for ${email} appeared in Mailpit at ${mailpitUrl()} (is Mailpit running?)`);
};

export const findResetPath = async (email: string): Promise<string> => {
  const message = await findResetMessage(email);
  const response = await fetch(`${mailpitUrl()}/api/v1/message/${message.ID}`);

  if (!response.ok) {
    throw new Error(`Mailpit returned ${response.status} for message ${message.ID}`);
  }

  const { Text, HTML } = (await response.json()) as { Text: string; HTML: string };
  const body = `${Text}\n${HTML}`.replace(/&amp;/g, '&');
  const match = body.match(/\/reset-password\/[^\s"'<>)\]]+/);

  if (!match) {
    throw new Error(`No reset link found in the e-mail for ${email}`);
  }

  return match[0];
};
