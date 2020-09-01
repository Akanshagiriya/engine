<?php if (count($vars['trends']) > 0) { ?>
    <tr>
        <td>
            <p
                <?php echo $emailStyles->getStyles('m-title--ltr', 'm-fonts'); ?>
            >
                Some highlights for you
            </p>
        </td>
    </tr>

    <tr>
        <td>
            <table
                border="0"
                cellpadding="0"
                cellspacing="0"
                class="m-responsiveTable"
                <?php echo $emailStyles->getStyles('m-maxWidth'); ?>>

                <?php foreach ($vars['activities'] as $activity) { ?>
                    <tr>
                        <td>This is a a post</td>
                    </tr> 
                <?php } ?>
            </table
        </td>
    </tr>
<?php } ?>

<?php if ($vars['hasDigestActivity']) { ?>
    <tr>
        <td>
        <p
            <?php echo $emailStyles->getStyles('m-title--ltr', 'm-fonts'); ?>
        >
            Your activity
        </p>
        </td>
    </tr>

    <tr>
        <td>
            <table
                border="0"
                cellpadding="0"
                cellspacing="0"
                class="m-responsiveTable"
                <?php echo $emailStyles->getStyles('m-digest__yourActivity'); ?>>

                <tr>
                    <td>Unread Notifications</td>
                    <td><?php echo $vars['unreadNotificationsCount']; ?></td>
                </tr>
            </table>
        </td>
    </tr>
<?php } ?>