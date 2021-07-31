GO
/****** Object:  StoredProcedure [dbo].[Data_Syahbandar]    Script Date: 11/4/2020 4:08:30 PM ******/
SET ANSI_NULLS ON
GO
SET QUOTED_IDENTIFIER ON
GO

alter PROCEDURE [dbo].[sp_single_permission](@MenuName NVARCHAR(100))
AS
BEGIN
    -- SET NOCOUNT ON added to prevent extra result sets from
    -- interfering with SELECT statements.
    SET NOCOUNT ON;

    WITH Summary AS (
        select a.menu_name,
               a.app_name,
               a.parent_id,
               a.icon,
               a.route_name,
               CAST(a.has_child as varchar) as has_child,
               cast(a.has_route as varchar) as has_route,
               cast(a.is_crud as varchar) as is_crud,
               a.order_line,
               b.menu_name as parent_name,
               idx.valuex   as 'index',
               store.valuex as store,
               edits.valuex as edits,
               erase.valuex as erase
        from permissions as a
                 left join permissions as b on a.parent_id = b.id
                 left join (
            select menu_name, 'Y' as valuex
            from permissions as a1
            where RIGHT(a1.name, 5) = 'index'
              and a1.menu_name = @MenuName
        ) as idx on a.menu_name = idx.menu_name

                 left join (
            select menu_name, 'Y' as valuex
            from permissions as a1
            where a1.menu_name = @MenuName
              and RIGHT(a1.name, 5) = 'store'

        ) as store on a.menu_name = store.menu_name

                 left join (
            select menu_name, 'Y' as valuex
            from permissions as a1
            where a1.menu_name = @MenuName
              and RIGHT(a1.name, 5) = 'edits'
        ) as edits on a.menu_name = edits.menu_name

                 left join (
            select menu_name, 'Y' as valuex
            from permissions as a1
            where a1.menu_name = @MenuName
              and RIGHT(a1.name, 5) = 'erase'
        ) as erase on a.menu_name = erase.menu_name

        where a.menu_name = @MenuName
    )

    SELECT top 1 *
    FROM Summary
    order by order_line
END
