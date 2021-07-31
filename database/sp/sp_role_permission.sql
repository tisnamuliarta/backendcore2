GO
/****** Object:  StoredProcedure [dbo].[sp_role_permissions]    Script Date: 7/31/2021 9:38:51 AM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO

ALTER PROCEDURE [dbo].[sp_role_permissions](@Role INTEGER)
AS
BEGIN
    -- SET NOCOUNT ON added to prevent extra result sets from
    -- interfering with SELECT statements.
    SET NOCOUNT ON;

    WITH Summary AS (
        select a.menu_name               as permission,
               ISNULL(idx.valuex, 'N')   as 'index',
               ISNULL(store.valuex, 'N') as store,
               ISNULL(edits.valuex, 'N') as edits,
               ISNULL(erase.valuex, 'N') as erase,
               a.order_line
        from permissions as a
                 left join role_has_permissions as b on a.id = b.permission_id
                 left join (
            select menu_name, 'Y' as valuex
            from permissions as a1
                     left join role_has_permissions as b on a1.id = b.permission_id
            where b.role_id = @Role
              and  RIGHT(a1.name, 5) = 'index'
            group by menu_name
        ) as idx on a.menu_name = idx.menu_name

                 left join (
            select menu_name, 'Y' as valuex
            from permissions as a1
                     left join role_has_permissions as b on a1.id = b.permission_id
            where b.role_id = @Role
              and RIGHT(a1.name, 5) = 'store'
            group by menu_name
        ) as store on a.menu_name = store.menu_name

                 left join (
            select menu_name, 'Y' as valuex
            from permissions as a1
                     left join role_has_permissions as b on a1.id = b.permission_id
            where b.role_id = @Role
              and RIGHT(a1.name, 5) = 'edits'
            group by menu_name
        ) as edits on a.menu_name = edits.menu_name

                 left join (
            select menu_name, 'Y' as valuex
            from permissions as a1
                     left join role_has_permissions as b on a1.id = b.permission_id
            where b.role_id = @Role
              and RIGHT(a1.name, 5) = 'erase'
            group by menu_name
        ) as erase on a.menu_name = erase.menu_name

        where b.role_id = @Role
        group by a.menu_name, idx.valuex, store.valuex,
                 edits.valuex, erase.valuex, a.order_line
    )

    SELECT *
    FROM Summary
    order by order_line
END
